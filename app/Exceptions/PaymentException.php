<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentException extends RuntimeException
{
    private bool $alreadyPaid = false;

    private bool $networkFailure = false;

    public static function nothingToPay(): self
    {
        $exception = new self('Booking ini sudah lunas.');
        $exception->alreadyPaid = true;

        return $exception;
    }

    public function isAlreadyPaid(): bool
    {
        return $this->alreadyPaid;
    }

    public static function gatewayUnavailable(string $detail): self
    {
        return new self('Pembayaran tidak dapat diproses saat ini, silakan coba lagi. ('.$detail.')');
    }

    /**
     * The request never got an answer, so the gateway may still have accepted the transaction.
     */
    public static function gatewayUnreachable(string $detail): self
    {
        $exception = self::gatewayUnavailable($detail);
        $exception->networkFailure = true;

        return $exception;
    }

    public function isNetworkFailure(): bool
    {
        return $this->networkFailure;
    }

    public static function downPaymentUnavailable(): self
    {
        return new self('Pembayaran DP tidak tersedia untuk booking ini.');
    }

    public static function gatewayNotConfigured(): self
    {
        return new self('Midtrans server key is not configured. Set MIDTRANS_SERVER_KEY.');
    }
}
