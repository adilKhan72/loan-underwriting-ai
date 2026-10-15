<?php

namespace App\Enums;

enum DocumentType: string
{
    case Paystub = 'paystub';
    case BankStatement = 'bank_statement';
}
