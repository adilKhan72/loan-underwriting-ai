<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Money;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

class PaymentToIncome implements Rule
{
    public function __construct(
        private readonly float $referAbove,
        private readonly float $failAbove,
    ) {}

    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        $income = $facts->verifiedMonthlyGrossCents;
        $payment = $facts->estimatedMonthlyPaymentCents;

        if (! $income) {
            return new RuleResult('PAYMENT_TO_INCOME', RuleOutcome::Skip, 'No verified income to measure affordability against.', ['estimated_monthly_payment_cents' => $payment]);
        }

        $ratio = round($payment / $income, 4);
        $values = ['estimated_monthly_payment_cents' => $payment, 'verified_monthly_gross_cents' => $income, 'ratio' => $ratio];
        $detail = 'Estimated payment '.Money::format($payment).' is '.round($ratio * 100, 1).'% of verified monthly income';

        return match (true) {
            $ratio > $this->failAbove => new RuleResult('PAYMENT_TO_INCOME', RuleOutcome::Fail, "{$detail}, above the ".($this->failAbove * 100).'% limit.', $values),
            $ratio > $this->referAbove => new RuleResult('PAYMENT_TO_INCOME', RuleOutcome::Refer, "{$detail}, above the ".($this->referAbove * 100).'% review threshold.', $values),
            default => new RuleResult('PAYMENT_TO_INCOME', RuleOutcome::Pass, "{$detail}.", $values),
        };
    }
}
