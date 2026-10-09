<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Events\BookingNeedsReview;
use App\Models\Booking;
use App\Services\UnitNightLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredHolds implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $ledger = app(UnitNightLedger::class);

        DB::transaction(function () use ($ledger) {
            $expired = Booking::where('status', BookingStatus::PendingPayment)
                ->where('hold_expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            if ($expired->isEmpty()) {
                return;
            }

            // Per model so the activity log observer records each change.
            $expired->each(fn (Booking $booking) => $this->release($booking));
            $ledger->release($expired->modelKeys());
        });
    }

    /**
     * A booking that already took a down payment never just expires: the money would vanish from
     * every open view. It goes to manual review, where staff move it or cancel it with a refund.
     */
    private function release(Booking $booking): void
    {
        if ($booking->paid_amount === 0) {
            $booking->update(['status' => BookingStatus::Expired]);

            return;
        }

        $booking->update(['status' => BookingStatus::NeedsReview, 'review_started_at' => now()]);
        BookingNeedsReview::dispatch($booking);
    }
}
