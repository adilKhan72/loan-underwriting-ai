<?php

namespace App\Listeners;

use App\Enums\ApplicationStatus;
use App\Enums\ExplanationStatus;
use App\Events\ApplicationDocumentsProcessed;
use App\Jobs\ExplainDecision;
use App\Underwriting\FactsBuilder;
use App\Underwriting\Policies\UnderwritingPolicy;
use App\Underwriting\RuleResult;
use App\Underwriting\RulesEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class MakeDecision implements ShouldQueue
{
    public function __construct(
        private readonly FactsBuilder $facts,
        private readonly RulesEngine $engine,
        private readonly UnderwritingPolicy $policy,
    ) {}

    public function handle(ApplicationDocumentsProcessed $event): void
    {
        $application = $event->application->fresh();

        // Idempotent: a redelivered event must not create a second decision.
        if ($application->status === ApplicationStatus::Decided) {
            return;
        }

        $facts = $this->facts->build($application, $this->policy->annualRate());
        $result = $this->engine->evaluate($this->policy, $facts);

        $decision = DB::transaction(function () use ($application, $facts, $result) {
            $decision = $application->decisions()->create([
                'policy_version' => $this->policy->version(),
                'outcome' => $result['outcome'],
                'rule_results' => array_map(fn (RuleResult $r) => $r->toArray(), $result['results']),
                'facts' => $facts->toArray(),
                'explanation_status' => ExplanationStatus::Pending,
                'decided_at' => now(),
            ]);

            $application->forceFill(['status' => ApplicationStatus::Decided])->save();

            return $decision;
        });

        // Prose comes after the decision is safely stored; the decision never waits on the LLM.
        ExplainDecision::dispatch($decision)->afterCommit();
    }
}
