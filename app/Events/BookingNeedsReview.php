<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised once, when a paid booking loses its units and needs a manual decision.
 * Dispatched after commit so a failing listener cannot roll back the payment.
 */
class BookingNeedsReview implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Booking $booking) {}
}
