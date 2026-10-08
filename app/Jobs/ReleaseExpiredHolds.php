<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseExpiredHolds implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Booking::where('status', BookingStatus::PendingPayment)
            ->where('hold_expires_at', '<=', now())
            ->update(['status' => BookingStatus::Expired]);
    }
}
