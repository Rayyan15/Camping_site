<?php

namespace App\Services;

use App\Models\Booking;

/**
 * priceDifference is new total minus old total: positive means the guest owes more, negative means overpaid.
 */
final readonly class RescheduleResult
{
    public function __construct(
        public Booking $booking,
        public int $priceDifference,
    ) {}
}
