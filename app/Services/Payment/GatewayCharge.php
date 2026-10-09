<?php

namespace App\Services\Payment;

/**
 * What a hosted payment page needs to know, independent of whether a booking or a QR order pays.
 */
final readonly class GatewayCharge
{
    /**
     * @param  array{name?: string|null, phone?: string|null, email?: string|null}  $customer
     * @param  array<int, string>  $enabledPayments  empty means every method the gateway offers
     */
    public function __construct(
        public string $reference,
        public int $amount,
        public string $label,
        public array $customer,
        public string $finishUrl,
        public array $enabledPayments = [],
    ) {}
}
