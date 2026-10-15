<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

class PaystubVerified implements Rule
{
    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        if (! $facts->hasVerifiedPaystub) {
            return new RuleResult('PAYSTUB_VERIFIED', RuleOutcome::Refer, 'No paystub could be verified automatically; income must be verified manually.');
        }

        if ($facts->verifiedMonthlyGrossCents === null) {
            return new RuleResult('PAYSTUB_VERIFIED', RuleOutcome::Refer, 'Paystub verified but pay frequency or gross pay is missing, so monthly income cannot be computed.');
        }

        return new RuleResult('PAYSTUB_VERIFIED', RuleOutcome::Pass, 'A paystub was verified automatically.');
    }
}
