<?php

namespace App\Enums;

enum DecisionOutcome: string
{
    case Approve = 'approve';
    case Refer = 'refer';
    case Decline = 'decline';
}
