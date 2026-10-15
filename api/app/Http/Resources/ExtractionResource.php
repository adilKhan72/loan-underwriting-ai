<?php

namespace App\Http\Resources;

use App\Models\Extraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Extraction */
class ExtractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'data' => $this->data,
            'errors' => $this->errors ?? [],
            'redactions' => $this->redactions ?? (object) [],
            'provider' => $this->provider,
            'attempts' => $this->attempts,
            'created_at' => $this->created_at,
        ];
    }
}
