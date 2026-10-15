<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Money;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

class MinimumVerifiedIncome implements Rule
{
    public function __construct(private readonly int $minimumMonthlyCents) {}

    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        $income = $facts->verifiedMonthlyGrossCents;
        $values = ['verified_monthly_gross_cents' => $income, 'minimum_cents' => $this->minimumMonthlyCents];

        if ($income === null) {
            return new RuleResult('MIN_VERIFIED_INCOME', RuleOutcome::Skip, 'No verified income to evaluate.', $values);
        }

        return $income >= $this->minimumMonthlyCents
            ? new RuleResult('MIN_VERIFIED_INCOME', RuleOutcome::Pass, 'Verified monthly income '.Money::format($income).' meets the '.Money::format($this->minimumMonthlyCents).' minimum.', $values)
            : new RuleResult('MIN_VERIFIED_INCOME', RuleOutcome::Fail, 'Verified monthly income '.Money::format($income).' is below the '.Money::format($this->minimumMonthlyCents).' minimum.', $values);
    }
}
