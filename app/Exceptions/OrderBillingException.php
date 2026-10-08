<?php

namespace App\Exceptions;

use RuntimeException;

class OrderBillingException extends RuntimeException
{
    public static function noActiveBooking(): self
    {
        return new self('Lokasi ini tidak punya booking aktif, pesanan tidak bisa ditagihkan ke booking.');
    }

    public static function alreadySettled(): self
    {
        return new self('Pesanan ini sudah lunas atau ditagihkan ke booking.');
    }
}
