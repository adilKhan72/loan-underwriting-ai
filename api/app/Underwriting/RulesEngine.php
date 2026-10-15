<?php

namespace App\Underwriting;

use App\Enums\DecisionOutcome;
use App\Enums\RuleOutcome;
use App\Underwriting\Policies\UnderwritingPolicy;

class RulesEngine
{
    /**
     * Every rule always runs (no short-circuit), so the decision records the full picture for the underwriter.
     *
     * @return array{outcome: DecisionOutcome, results: list<RuleResult>}
     */
    public function evaluate(UnderwritingPolicy $policy, UnderwritingFacts $facts): array
    {
        $results = array_map(fn (Rule $rule) => $rule->evaluate($facts), $policy->rules());
        $outcomes = array_map(fn (RuleResult $r) => $r->outcome, $results);

        $outcome = match (true) {
            in_array(RuleOutcome::Fail, $outcomes, true) => DecisionOutcome::Decline,
            in_array(RuleOutcome::Refer, $outcomes, true) => DecisionOutcome::Refer,
            default => DecisionOutcome::Approve,
        };

        return ['outcome' => $outcome, 'results' => $results];
    }
}
