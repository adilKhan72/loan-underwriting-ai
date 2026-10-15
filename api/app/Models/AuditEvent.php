<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['loan_application_id', 'position', 'event', 'actor_type', 'actor_id', 'data', 'prev_hash', 'hash', 'occurred_at'])]
class AuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'data' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
