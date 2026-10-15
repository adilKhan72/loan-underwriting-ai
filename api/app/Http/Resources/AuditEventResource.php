<?php

namespace App\Http\Resources;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditEvent */
class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'event' => $this->event,
            'actor' => ['type' => $this->actor_type, 'id' => $this->actor_id],
            'data' => $this->data,
            'hash' => $this->hash,
            'prev_hash' => $this->prev_hash,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
