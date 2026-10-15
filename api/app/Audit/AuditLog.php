<?php

namespace App\Audit;

use App\Models\AuditEvent;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Append-only, hash-chained audit trail per application.
 *
 * $data must be non-PII facts (statuses, counts, codes, versions). Callers pass what happened,
 * never who the applicant is or what their documents say (ADR-0004).
 */
class AuditLog
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(LoanApplication|string $application, string $event, array $data = [], ?User $actor = null): AuditEvent
    {
        $applicationId = $application instanceof LoanApplication ? $application->id : $application;

        return DB::transaction(function () use ($applicationId, $event, $data, $actor) {
            // Serialise appends per application so two writers can't fork the chain.
            LoanApplication::query()->whereKey($applicationId)->lockForUpdate()->first();

            $previous = AuditEvent::query()
                ->where('loan_application_id', $applicationId)
                ->orderByDesc('position')
                ->first(['position', 'hash']);

            $attributes = [
                'id' => strtolower((string) Str::ulid()),
                'loan_application_id' => $applicationId,
                'position' => ($previous->position ?? 0) + 1,
                'event' => $event,
                'actor_type' => $actor ? 'user' : 'system',
                'actor_id' => $actor?->getKey() ? (string) $actor->getKey() : null,
                'data' => $data,
                'prev_hash' => $previous?->hash,
            ];

            // forceCreate: the id is part of the hash, so it must be stored exactly as hashed.
            return AuditEvent::forceCreate($attributes + [
                'hash' => self::hash($attributes),
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * The id is a ULID, so the timestamp is part of what's hashed without depending on DB time precision;
     * position is hashed too, so reordering events breaks the chain.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function hash(array $attributes): string
    {
        $canonical = [
            'id' => $attributes['id'],
            'loan_application_id' => $attributes['loan_application_id'],
            'position' => $attributes['position'],
            'event' => $attributes['event'],
            'actor_type' => $attributes['actor_type'],
            'actor_id' => $attributes['actor_id'],
            'data' => self::sortKeys($attributes['data']),
            'prev_hash' => $attributes['prev_hash'],
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * Walks the chain in order. Returns the id of the first event whose link or content doesn't verify, or null.
     */
    public function firstBrokenEvent(string $applicationId): ?string
    {
        $previous = null;
        $position = 0;

        foreach (AuditEvent::query()->where('loan_application_id', $applicationId)->orderBy('position')->cursor() as $event) {
            $expected = self::hash([
                'id' => $event->id,
                'loan_application_id' => $event->loan_application_id,
                'position' => $event->position,
                'event' => $event->event,
                'actor_type' => $event->actor_type,
                'actor_id' => $event->actor_id,
                'data' => $event->data,
                'prev_hash' => $event->prev_hash,
            ]);

            if ($event->position !== ++$position || $event->prev_hash !== $previous || ! hash_equals($expected, $event->hash)) {
                return $event->id;
            }

            $previous = $event->hash;
        }

        return null;
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::sortKeys(...), $value);
    }
}
