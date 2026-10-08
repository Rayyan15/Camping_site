<?php

namespace App\Exceptions;

class InvalidPaymentSignatureException extends PaymentException
{
    public static function make(): self
    {
        return new self('Payment notification signature is invalid.');
    }
}
