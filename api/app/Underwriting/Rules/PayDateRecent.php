<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

class PayDateRecent implements Rule
{
    public function __construct(private readonly int $maxAgeDays) {}

    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        if ($facts->latestPayDate === null) {
            return new RuleResult('PAY_DATE_RECENT', RuleOutcome::Skip, 'No verified pay date.');
        }

        $age = (int) $facts->latestPayDate->diffInDays($facts->submittedAt, false);
        $values = ['latest_pay_date' => $facts->latestPayDate->toDateString(), 'age_days' => $age, 'max_age_days' => $this->maxAgeDays];

        if ($age < 0) {
            return new RuleResult('PAY_DATE_RECENT', RuleOutcome::Refer, 'Latest pay date is after the submission date; the paystub may be altered or mis-read.', $values);
        }

        return $age <= $this->maxAgeDays
            ? new RuleResult('PAY_DATE_RECENT', RuleOutcome::Pass, "Latest pay date is {$age} days before submission.", $values)
            : new RuleResult('PAY_DATE_RECENT', RuleOutcome::Refer, "Latest paystub is {$age} days old; a recent paystub is needed.", $values);
    }
}
