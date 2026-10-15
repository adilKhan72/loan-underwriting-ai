<?php

namespace App\Underwriting;

interface Rule
{
    public function evaluate(UnderwritingFacts $facts): RuleResult;
}
