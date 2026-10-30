<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The owner counts the booking window in calendar months (30 Oct opens up to 30 Mar),
 * so days would drift by month length.
 */
final class BookingWindow
{
    public static function lastCheckIn(): CarbonImmutable
    {
        return CarbonImmutable::today()->addMonthsNoOverflow((int) config('booking.max_advance_months'));
    }
}
