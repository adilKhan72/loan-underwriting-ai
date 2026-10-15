<?php

namespace App\Http\Resources;

use App\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Applicant */
class ApplicantResource extends JsonResource
{
    /**
     * Never exposes the full SSN or date of birth, only what an underwriter needs to identify the applicant.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'ssn_masked' => $this->maskedSsn(),
        ];
    }
}
