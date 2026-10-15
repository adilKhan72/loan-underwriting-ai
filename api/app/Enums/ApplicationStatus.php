<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Decided = 'decided';

    public function acceptsDocuments(): bool
    {
        return $this === self::Draft;
    }
}
