<?php

namespace App\Services;

final readonly class RefundQuote
{
    public function __construct(
        public int $percent,
        public int $amount,
        public int $daysBefore,
    ) {}
}
