<?php

namespace App\Underwriting\Policies;

use App\Underwriting\Rules\DocumentsReadable;
use App\Underwriting\Rules\ExtractionConfidence;
use App\Underwriting\Rules\MinimumVerifiedIncome;
use App\Underwriting\Rules\OverdraftHistory;
use App\Underwriting\Rules\PayDateRecent;
use App\Underwriting\Rules\PaymentToIncome;
use App\Underwriting\Rules\PayrollDepositMatches;
use App\Underwriting\Rules\PaystubVerified;
use App\Underwriting\Rules\StatedIncomeConsistent;

/**
 * Thresholds live here, in code, so changing policy means a reviewed change and a new version
 * (PolicyV2), never an in-place edit that would make old decisions unreproducible.
 */
final class PolicyV1 implements UnderwritingPolicy
{
    public function version(): string
    {
        return 'v1';
    }

    public function annualRate(): float
    {
        return 0.36;
    }

    public function rules(): array
    {
        return [
            new DocumentsReadable,
            new PaystubVerified,
            new MinimumVerifiedIncome(minimumMonthlyCents: 150_000),
            new StatedIncomeConsistent(tolerance: 0.20),
            new PaymentToIncome(referAbove: 0.15, failAbove: 0.25),
            new PayDateRecent(maxAgeDays: 45),
            new PayrollDepositMatches(tolerance: 0.05),
            new OverdraftHistory(referAtOrAbove: 3),
            new ExtractionConfidence(minimum: 0.70),
        ];
    }
}
