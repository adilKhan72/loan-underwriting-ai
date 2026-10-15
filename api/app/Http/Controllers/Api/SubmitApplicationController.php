<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Events\ApplicationDocumentsProcessed;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoanApplicationResource;
use App\Jobs\ProcessDocument;
use App\Models\LoanApplication;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

class SubmitApplicationController extends Controller
{
    /**
     * Locks the application and queues one ProcessDocument job per document as a single batch.
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

        $applicationId = $application->id;

        // allowFailures + finally: one unreadable document must not block the decision on the rest;
        // the rules engine sees which documents need review.
        Bus::batch($application->documents->map(fn ($document) => new ProcessDocument($document))->all())
            ->name("application:{$applicationId}")
            ->allowFailures()
            ->finally(fn (Batch $batch) => ApplicationDocumentsProcessed::dispatch(LoanApplication::findOrFail($applicationId)))
            ->dispatch();

        return new LoanApplicationResource($application->load('applicant', 'documents.latestExtraction'));
    }
}
