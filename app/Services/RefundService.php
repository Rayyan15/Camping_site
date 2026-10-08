<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\RefundException;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\RefundPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * Refund estimate from the owner's policy tiers: the largest min_days_before not above the days left.
     */
    public function calculate(Booking $booking, ?CarbonInterface $now = null): RefundQuote
    {
        $today = ($now ?? now())->startOfDay();
        $daysBefore = (int) $today->diffInDays($booking->check_in->startOfDay(), false);

        $percent = (int) RefundPolicy::where('min_days_before', '<=', $daysBefore)
            ->orderByDesc('min_days_before')
            ->value('percent');

        $amount = intdiv($booking->paid_amount * $percent, 100);

        return new RefundQuote($percent, $amount, $daysBefore);
    }

    public function isCancellable(Booking $booking, ?CarbonInterface $now = null): bool
    {
        if (! in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Paid], true)) {
            return false;
        }

        return $this->calculate($booking, $now)->daysBefore >= 1;
    }

    public function hasOpenRequest(Booking $booking): bool
    {
        return Refund::where('booking_id', $booking->id)
            ->whereIn('status', RefundStatus::open())
            ->exists();
    }

    /**
     * Customer cancellation. A pending booking is cancelled outright; a paid one files a refund request
     * and keeps its units held until the owner decides. Returns the refund when one was created.
     */
    public function cancel(Booking $booking, string $reason, ?int $userId = null): ?Refund
    {
        if ($booking->status === BookingStatus::Paid) {
            return $this->request($booking, $reason, $userId);
        }

        DB::transaction(function () use ($booking) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::PendingPayment || ! $this->isCancellable($locked)) {
                throw RefundException::notCancellable();
            }

            $locked->update(['status' => BookingStatus::Cancelled, 'hold_expires_at' => null]);
        });

        return null;
    }

    public function request(Booking $booking, string $reason, ?int $userId = null): Refund
    {
        return DB::transaction(function () use ($booking, $reason, $userId) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::Paid || ! $this->isCancellable($locked)) {
                throw RefundException::notCancellable();
            }

            if ($this->hasOpenRequest($locked)) {
                throw RefundException::alreadyRequested();
            }

            return Refund::create([
                'booking_id' => $locked->id,
                'amount' => $this->calculate($locked)->amount,
                'reason' => $reason,
                'status' => RefundStatus::Requested,
                'requested_by' => $userId,
            ]);
        });
    }

    public function approve(Refund $refund, int $userId): Refund
    {
        return $this->transition($refund, RefundStatus::Approved, function (Refund $locked) use ($userId) {
            Booking::whereKey($locked->booking_id)->update(['status' => BookingStatus::Refunded]);

            return ['approved_by' => $userId];
        });
    }

    public function reject(Refund $refund, int $userId, string $note): Refund
    {
        return $this->transition($refund, RefundStatus::Rejected, fn (Refund $locked) => [
            'approved_by' => $userId,
            'reason' => trim($locked->reason."\nCatatan penolakan: ".$note),
        ]);
    }

    public function markPaid(Refund $refund, int $userId): Refund
    {
        return $this->transition($refund, RefundStatus::Paid, function (Refund $locked) use ($userId) {
            // Ledger row only: booking.paid_amount stays the gross amount received from the customer.
            $locked->booking->payments()->create([
                'direction' => PaymentDirection::Out,
                'method' => PaymentMethod::Manual,
                'amount' => $locked->amount,
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'recorded_by' => $userId,
            ]);

            return ['approved_by' => $userId];
        });
    }

    /**
     * @param  callable(Refund): array<string, mixed>  $apply  side effects; returns extra columns to save
     */
    private function transition(Refund $refund, RefundStatus $to, callable $apply): Refund
    {
        return DB::transaction(function () use ($refund, $to, $apply) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if (! $this->canTransition($locked->status, $to)) {
                throw RefundException::invalidTransition($locked->status, $to);
            }

            $locked->update(['status' => $to] + $apply($locked));

            return $locked;
        });
    }

    private function canTransition(RefundStatus $from, RefundStatus $to): bool
    {
        return match ($from) {
            RefundStatus::Requested => in_array($to, [RefundStatus::Approved, RefundStatus::Rejected], true),
            RefundStatus::Approved => $to === RefundStatus::Paid,
            default => false,
        };
    }
}
