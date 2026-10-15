<?php

namespace App\Enums;

enum ExplanationStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    // The decision stands without prose; underwriters still see every rule result.
    case Unavailable = 'unavailable';
}
