<?php

namespace App\Http\Resources;

use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LoanApplication */
class LoanApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'amount_requested_cents' => $this->amount_requested_cents,
            'term_months' => $this->term_months,
            'purpose' => $this->purpose,
            'stated_monthly_income_cents' => $this->stated_monthly_income_cents,
            'applicant' => new ApplicantResource($this->whenLoaded('applicant')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'submitted_at' => $this->submitted_at,
            'created_at' => $this->created_at,
        ];
    }
}
