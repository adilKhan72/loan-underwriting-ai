<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

class OverdraftHistory implements Rule
{
    public function __construct(private readonly int $referAtOrAbove) {}

    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        $count = $facts->overdraftCount;

        if ($count === null) {
            return new RuleResult('OVERDRAFT_HISTORY', RuleOutcome::Skip, 'No verified bank statement.');
        }

        $values = ['overdraft_count' => $count, 'refer_at_or_above' => $this->referAtOrAbove];

        return $count < $this->referAtOrAbove
            ? new RuleResult('OVERDRAFT_HISTORY', RuleOutcome::Pass, "{$count} overdraft/NSF event(s) on the statement.", $values)
            : new RuleResult('OVERDRAFT_HISTORY', RuleOutcome::Refer, "{$count} overdraft/NSF events on the statement.", $values);
    }
}
