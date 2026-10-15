<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmitDispatchesProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_queues_one_job_per_document_as_a_failure_tolerant_batch(): void
    {
        Bus::fake();
        Sanctum::actingAs(User::factory()->create());

        $application = LoanApplication::factory()->create();
        Document::factory()->for($application)->create();
        Document::factory()->bankStatement()->for($application)->create();

        $this->postJson("/api/applications/{$application->id}/submit")->assertOk();

        Bus::assertBatched(function (PendingBatch $batch) use ($application) {
            return $batch->name === "application:{$application->id}"
                && $batch->jobs->count() === 2
                && $batch->jobs->every(fn ($job) => $job instanceof ProcessDocument)
                && $batch->allowsFailures();
        });
    }
}
