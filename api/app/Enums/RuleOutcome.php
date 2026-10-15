<?php

namespace App\Enums;

enum RuleOutcome: string
{
    case Pass = 'pass';
    case Refer = 'refer';
    case Fail = 'fail';
    // The rule had nothing to evaluate (e.g. no bank statement uploaded). Neutral in aggregation.
    case Skip = 'skip';
}
