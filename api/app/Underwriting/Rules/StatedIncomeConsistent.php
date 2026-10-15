<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Money;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

/**
 * Only overstatement matters: earning more than stated is not a risk signal.
 */
class StatedIncomeConsistent implements Rule
{
    public function __construct(private readonly float $tolerance) {}

    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        $verified = $facts->verifiedMonthlyGrossCents;
        $stated = $facts->statedMonthlyIncomeCents;
        $values = ['verified_monthly_gross_cents' => $verified, 'stated_monthly_income_cents' => $stated, 'tolerance' => $this->tolerance];

        if ($verified === null || $stated === 0) {
            return new RuleResult('STATED_INCOME_CONSISTENT', RuleOutcome::Skip, 'No verified income to compare with stated income.', $values);
        }

        $shortfall = ($stated - $verified) / $stated;

        return $shortfall <= $this->tolerance
            ? new RuleResult('STATED_INCOME_CONSISTENT', RuleOutcome::Pass, 'Verified income is consistent with stated income.', $values)
            : new RuleResult('STATED_INCOME_CONSISTENT', RuleOutcome::Refer, 'Stated income '.Money::format($stated).' is '.round($shortfall * 100).'% above verified income '.Money::format($verified).'.', $values);
    }
}
