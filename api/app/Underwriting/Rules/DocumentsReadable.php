<?php

namespace App\Underwriting\Rules;

use App\Enums\RuleOutcome;
use App\Underwriting\Rule;
use App\Underwriting\RuleResult;
use App\Underwriting\UnderwritingFacts;

class DocumentsReadable implements Rule
{
    public function evaluate(UnderwritingFacts $facts): RuleResult
    {
        $count = $facts->documentsNeedingReview;

        return $count === 0
            ? new RuleResult('DOCUMENTS_READABLE', RuleOutcome::Pass, 'All documents were processed automatically.')
            : new RuleResult('DOCUMENTS_READABLE', RuleOutcome::Refer, "{$count} document(s) could not be verified automatically and need manual review.", ['documents_needing_review' => $count]);
    }
}
