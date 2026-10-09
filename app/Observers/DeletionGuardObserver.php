<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Exceptions\CannotDeleteReferencedRecord;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;

/**
 * Foreign keys cascade on delete, so removing a referenced row would silently erase paid bookings
 * and orphan their payments. Guarding at model level works on every driver, including sqlite.
 */
class DeletionGuardObserver
{
    public function deleting(Model $model): void
    {
        $blocked = match (true) {
            $model instanceof Customer => $model->bookings()->exists(),
            $model instanceof Unit => $model->bookingUnits()->exists(),
            $model instanceof Booking => $this->bookingHasFinancialTrail($model),
            default => false,
        };

        if (! $blocked) {
            return;
        }

        throw match (true) {
            $model instanceof Customer => CannotDeleteReferencedRecord::customer(),
            $model instanceof Unit => CannotDeleteReferencedRecord::unit(),
            default => CannotDeleteReferencedRecord::booking(),
        };
    }

    private function bookingHasFinancialTrail(Booking $booking): bool
    {
        $discardable = [BookingStatus::PendingPayment, BookingStatus::Expired, BookingStatus::Cancelled];

        return ! in_array($booking->status, $discardable, true)
            || $booking->payments()->exists()
            || $booking->refunds()->exists()
            || $booking->orders()->exists();
    }
}
