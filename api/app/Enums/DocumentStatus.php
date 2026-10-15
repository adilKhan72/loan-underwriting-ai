<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Extracted = 'extracted';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return in_array($this, [self::Extracted, self::NeedsReview, self::Failed], true);
    }
}
