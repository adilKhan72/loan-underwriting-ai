<?php

namespace App\Enums;

enum ExtractionStatus: string
{
    case Ok = 'ok';
    case NeedsReview = 'needs_review';
}
