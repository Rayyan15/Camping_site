<?php

namespace App\Contracts;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Booking;

interface PaymentGateway
{
    /**
     * Creates a hosted payment page for the given order id and returns the URL the customer is sent to.
     *
     * @throws PaymentException when the gateway cannot create the transaction
     */
    public function createTransaction(Booking $booking, string $orderId, int $amount): string;

    /**
     * Whether an incoming notification really comes from the gateway.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isAuthentic(array $payload): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function statusFrom(array $payload): PaymentStatus;
}
