<?php

namespace App\Enums;

enum PaymentDirection: string
{
    case In = 'in';
    case Out = 'out';
}
