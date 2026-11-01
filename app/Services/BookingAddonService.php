<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingAddonChangeException;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

/**
 * Guests often ask for an extra bed or the ATV trip after booking, so staff can replace the add-on lines.
 * Unit lines and pre-ordered food keep their price; only the add-on share of the subtotal is recalculated.
 */
class BookingAddonService
{
    private const EDITABLE_STATUSES = [BookingStatus::PendingPayment, BookingStatus::Paid, BookingStatus::CheckedIn, BookingStatus::NeedsReview];

    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @param  array<int, int>  $quantities  addon id => quantity, zero removes the add-on
     * @return int the change in total, positive when the guest owes more
     */
    public function replace(Booking $booking, array $quantities): int
    {
        return DB::transaction(function () use ($booking, $quantities) {
            $booking = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($booking->status, self::EDITABLE_STATUSES, true)) {
                throw new BookingAddonChangeException('Tambahan hanya bisa diubah sebelum tamu check-out.');
            }

            $oldAddonTotal = (int) $booking->addons()->sum('subtotal');
            $nights = (int) $booking->check_in->diffInDays($booking->check_out);
            $lines = $this->pricing->addonLines(array_filter($quantities, fn (int $qty): bool => $qty > 0), $nights);
            $newAddonTotal = array_sum(array_column($lines, 'subtotal'));

            $booking->addons()->delete();
            $booking->addons()->createMany($lines);

            $difference = $newAddonTotal - $oldAddonTotal;
            $booking->update([
                'subtotal' => $booking->subtotal + $difference,
                'total' => $booking->total + $difference,
            ]);

            return $difference;
        });
    }
}
