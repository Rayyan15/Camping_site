<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;

/**
 * Stand-in while payments are bank transfers confirmed by staff. It opens no hosted page and
 * rejects every webhook, so nothing outside the admin panel can mark a payment as paid.
 */
class ManualGateway implements PaymentGateway
{
    public function createTransaction(GatewayCharge $charge): string
    {
        throw PaymentException::gatewayUnavailable('pembayaran online tidak aktif');
    }

    public function isAuthentic(array $payload): bool
    {
        return false;
    }

    public function statusFrom(array $payload): PaymentStatus
    {
        return PaymentStatus::Pending;
    }
}
