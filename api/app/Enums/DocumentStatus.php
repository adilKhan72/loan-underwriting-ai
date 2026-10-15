<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Extracted = 'extracted';
    case Failed = 'failed';
}
