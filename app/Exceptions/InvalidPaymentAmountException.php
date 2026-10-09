<?php

namespace App\Exceptions;

/**
 * Extends the signature exception so the webhook answers 403 and the gateway stops trusting the call.
 */
class InvalidPaymentAmountException extends InvalidPaymentSignatureException
{
    public static function forReference(string $reference): self
    {
        return new self('Payment notification amount does not match payment '.$reference.'.');
    }
}
