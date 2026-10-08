<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public static function nothingToPay(): self
    {
        return new self('Booking ini sudah lunas.');
    }

    public static function gatewayUnavailable(string $detail): self
    {
        return new self('Pembayaran tidak dapat diproses saat ini, silakan coba lagi. ('.$detail.')');
    }
}
