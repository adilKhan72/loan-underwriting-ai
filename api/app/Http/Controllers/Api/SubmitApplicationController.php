<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use Illuminate\Validation\ValidationException;

class SubmitApplicationController extends Controller
{
    /**
     * Locks the application for underwriting. Document processing hooks in here in milestone 4.
     */
    public function __invoke(LoanApplication $application): LoanApplicationResource
    {
        abort_unless($application->status === ApplicationStatus::Draft, 409, 'Only draft applications can be submitted.');

        if (! $application->hasDocumentOfType(DocumentType::Paystub)) {
            throw ValidationException::withMessages([
                'documents' => 'At least one paystub is required before submitting.',
            ]);
        }

        $application->forceFill([
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        return new LoanApplicationResource($application->load('applicant', 'documents'));
    }
}
