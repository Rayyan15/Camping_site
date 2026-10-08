<?php

namespace App\Services\Payment;

final readonly class PaymentIntent
{
    public function __construct(
        public string $redirectUrl,
        public string $reference,
    ) {}
}
