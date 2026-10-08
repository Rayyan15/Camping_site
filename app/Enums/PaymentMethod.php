<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Gateway = 'gateway';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Manual = 'manual';
}
