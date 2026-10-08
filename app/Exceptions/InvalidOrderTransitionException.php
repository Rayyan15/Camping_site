<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Pesanan tidak bisa diubah dari {$from->value} ke {$to->value}.");
    }
}
