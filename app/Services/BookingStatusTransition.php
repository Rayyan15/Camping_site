<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Manual status moves an operator may make from the admin panel.
 * Payment, expiry and refund statuses are excluded because they only change
 * through the payment, hold-release and refund services.
 */
class BookingStatusTransition
{
    /**
     * @return array<int, BookingStatus>
     */
    public function allowedFrom(BookingStatus $current): array
    {
        return match ($current) {
            BookingStatus::PendingPayment => [BookingStatus::Cancelled],
            BookingStatus::Paid => [BookingStatus::CheckedIn],
            BookingStatus::CheckedIn => [BookingStatus::CheckedOut],
            default => [],
        };
    }

    /**
     * Options for the status select: the current status plus every allowed target.
     *
     * @return array<string, string>
     */
    public function optionsFor(BookingStatus $current): array
    {
        $options = [];

        foreach ([$current, ...$this->allowedFrom($current)] as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }

    public function canMove(BookingStatus $current, BookingStatus $target): bool
    {
        return in_array($target, $this->allowedFrom($current), true);
    }

    /**
     * Moves the booking under a row lock so two operators cannot apply the same step twice.
     * Saving through the model keeps the activity log observer in the loop.
     *
     * @throws InvalidBookingTransitionException when the locked row no longer allows the move
     */
    public function apply(Booking $booking, BookingStatus $target): Booking
    {
        return DB::transaction(function () use ($booking, $target): Booking {
            $locked = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->canMove($locked->status, $target)) {
                throw InvalidBookingTransitionException::between($locked->status, $target);
            }

            if ($target === BookingStatus::CheckedIn && $locked->check_in->isAfter(today())) {
                throw InvalidBookingTransitionException::beforeCheckInDate($locked->check_in);
            }

            // Money already received must leave through a refund, never vanish with a plain cancel.
            if ($target === BookingStatus::Cancelled && $locked->paid_amount > 0) {
                throw InvalidBookingTransitionException::holdsPayment();
            }

            $locked->update($target === BookingStatus::Cancelled
                ? ['status' => $target, 'hold_expires_at' => null]
                : ['status' => $target]);

            // Cancelling must free the nights, otherwise the ledger keeps blocking the unit.
            if ($target === BookingStatus::Cancelled) {
                app(UnitNightLedger::class)->release([$locked->getKey()]);
                app(PaymentService::class)->expirePendingPayments($locked);
            }

            $booking->refresh();

            return $locked;
        });
    }
}
