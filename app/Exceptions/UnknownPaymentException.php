<?php

namespace App\Exceptions;

class UnknownPaymentException extends PaymentException
{
    public static function forReference(string $reference): self
    {
        return new self('No payment found for reference '.$reference.'.');
    }
}
