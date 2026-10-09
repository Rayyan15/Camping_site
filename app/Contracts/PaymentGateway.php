<?php

namespace App\Contracts;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Services\Payment\GatewayCharge;

interface PaymentGateway
{
    /**
     * Creates a hosted payment page for the charge and returns the URL the customer is sent to.
     *
     * @throws PaymentException when the gateway cannot create the transaction
     */
    public function createTransaction(GatewayCharge $charge): string;

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
