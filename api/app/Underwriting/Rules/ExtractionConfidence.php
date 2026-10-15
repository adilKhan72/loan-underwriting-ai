<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

/**
 * Low model confidence never declines (ADR-0001): it sends the application to a person.
 */
class ExtractionConfidence implements Rule
{
    public function __construct(private readonly float $minimum) {}

    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        $confidence = $facts->minimumConfidence;

        if ($confidence === null) {
            return new RuleResult('EXTRACTION_CONFIDENCE', RuleOutcome::Skip, 'No automated extractions to assess.');
        }

        $values = ['minimum_confidence' => $confidence, 'threshold' => $this->minimum];

        return $confidence >= $this->minimum
            ? new RuleResult('EXTRACTION_CONFIDENCE', RuleOutcome::Pass, 'Automated extraction confidence is sufficient.', $values)
            : new RuleResult('EXTRACTION_CONFIDENCE', RuleOutcome::Refer, 'Automated extraction confidence ('.round($confidence, 2).') is below '.$this->minimum.'; figures need manual confirmation.', $values);
    }
}
