<?php

namespace App\Http\Resources;

use App\Models\Decision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Decision */
class DecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outcome' => $this->outcome,
            'policy_version' => $this->policy_version,
            'rule_results' => $this->rule_results,
            'explanation' => [
                'status' => $this->explanation_status,
                'underwriter_summary' => $this->underwriter_summary,
                'applicant_message' => $this->applicant_message,
                'principal_reasons' => $this->principal_reasons ?? [],
                'provider' => $this->explanation_provider,
            ],
            'decided_at' => $this->decided_at,
        ];
    }
}
