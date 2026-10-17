<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Pesanan tidak bisa diubah dari {$from->label()} ke {$to->label()}.");
    }

    public static function paidCannotBeCancelled(): self
    {
        return new self('Pesanan yang sudah dibayar tidak bisa dibatalkan.');
    }
}
