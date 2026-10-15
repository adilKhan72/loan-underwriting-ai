<?php

namespace App\Jobs;

use App\Enums\ExplanationStatus;
use App\Models\Decision;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExplainDecision implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(public Decision $decision) {}

    public function handle(AiServiceClient $ai): void
    {
        $decision = $this->decision;

        if ($decision->explanation_status === ExplanationStatus::Ready) {
            return;
        }

        $rules = array_map(
            fn (array $r) => ['code' => $r['code'], 'outcome' => $r['outcome'], 'message' => $r['message']],
            $decision->rule_results,
        );

        try {
            $explanation = $ai->explain($decision->outcome->value, $decision->policy_version, $rules);
        } catch (AiServiceRejected) {
            $this->markUnavailable();

            return;
        }

        $decision->update([
            'explanation_status' => ExplanationStatus::Ready,
            'underwriter_summary' => $explanation['underwriter_summary'],
            'applicant_message' => $explanation['applicant_message'],
            'principal_reasons' => $explanation['principal_reasons'],
            'explanation_provider' => $explanation['provider'],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->markUnavailable();
    }

    private function markUnavailable(): void
    {
        $this->decision->update(['explanation_status' => ExplanationStatus::Unavailable]);
    }
}
