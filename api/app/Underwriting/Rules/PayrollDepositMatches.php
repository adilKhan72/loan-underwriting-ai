<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Money;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

/**
 * Cross-checks two independent documents: paystub net pay should arrive as a payroll deposit.
 */
class PayrollDepositMatches implements Rule
{
    public function __construct(private readonly float $tolerance) {}

    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        $net = $facts->latestNetPayCents;

        if (! $facts->hasVerifiedBankStatement || $net === null) {
            return new RuleResult('PAYROLL_DEPOSIT_MATCHES', RuleOutcome::Skip, 'No verified bank statement and paystub pair to cross-check.');
        }

        $match = collect($facts->payrollDepositsCents)->first(fn (int $d) => abs($d - $net) <= $net * $this->tolerance);
        $values = ['net_pay_cents' => $net, 'payroll_deposits_cents' => $facts->payrollDepositsCents, 'tolerance' => $this->tolerance];

        return $match !== null
            ? new RuleResult('PAYROLL_DEPOSIT_MATCHES', RuleOutcome::Pass, 'A payroll deposit of '.Money::format($match).' matches paystub net pay '.Money::format($net).'.', $values)
            : new RuleResult('PAYROLL_DEPOSIT_MATCHES', RuleOutcome::Refer, 'No payroll deposit on the bank statement matches paystub net pay '.Money::format($net).'.', $values);
    }
}
