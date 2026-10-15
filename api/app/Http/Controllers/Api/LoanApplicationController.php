<?php

namespace App\Http\Controllers\Api;

use App\Audit\AuditLog;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\Applicant;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LoanApplicationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $applications = LoanApplication::query()
            ->with('applicant')
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return LoanApplicationResource::collection($applications);
    }

    public function store(StoreLoanApplicationRequest $request, AuditLog $audit): JsonResponse
    {
        $application = DB::transaction(function () use ($request, $audit) {
            $applicant = Applicant::create($request->validated('applicant'));
            $application = $applicant->loanApplications()->create($request->safe()->except('applicant'));

            $audit->record($application, 'application.created', [
                'amount_requested_cents' => $application->amount_requested_cents,
                'term_months' => $application->term_months,
            ], $request->user());

            return $application;
        });

        return (new LoanApplicationResource($application->load('applicant', 'documents')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LoanApplication $application): LoanApplicationResource
    {
        return new LoanApplicationResource($application->load('applicant', 'documents.latestExtraction', 'latestDecision'));
    }
}
