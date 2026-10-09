<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\BookingReviewException;
use App\Exceptions\RefundException;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(
        private readonly UnitNightLedger $ledger,
        private readonly BookingAuditTrail $audit,
    ) {}

    /**
     * Refund estimate from the owner's policy tiers: the largest min_days_before not above the days left.
     */
    public function calculate(Booking $booking, ?CarbonInterface $now = null): RefundQuote
    {
        $today = ($now ?? now())->copy()->startOfDay();
        $daysBefore = (int) $today->diffInDays($booking->check_in->copy()->startOfDay(), false);

        $percent = (int) RefundPolicy::where('min_days_before', '<=', $daysBefore)
            ->orderByDesc('min_days_before')
            ->value('percent');

        $amount = intdiv($booking->paid_amount * $percent, 100);

        return new RefundQuote($percent, $amount, $daysBefore);
    }

    /**
     * Paid bookings and pending bookings that already took a down payment hold the guest's money,
     * so cancelling them must go through the refund tiers instead of a plain cancel.
     */
    public function holdsGuestMoney(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Paid
            || ($booking->status === BookingStatus::PendingPayment && $booking->paid_amount > 0);
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
     * Customer cancellation, kept for callers that only need the refund. Null means no refund request
     * exists afterwards: a pending booking was cancelled, or a 0% paid booking was cancelled without one.
     */
    public function cancel(Booking $booking, string $reason, ?int $userId = null): ?Refund
    {
        return $this->cancelWithOutcome($booking, $reason, $userId)->refund;
    }

    /**
     * Customer cancellation with an explicit outcome. A pending booking is cancelled outright; a paid one
     * files a refund request and keeps its units held until the owner decides, unless the policy tier is
     * 0%, in which case it is cancelled right away and no zero-value refund is ever created.
     */
    public function cancelWithOutcome(Booking $booking, string $reason, ?int $userId = null): CancellationResult
    {
        if ($this->holdsGuestMoney($booking)) {
            return $this->cancelPaid($booking, $reason, $userId);
        }

        DB::transaction(function () use ($booking) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::PendingPayment || $locked->paid_amount > 0 || ! $this->isCancellable($locked)) {
                throw RefundException::notCancellable();
            }

            $locked->update(['status' => BookingStatus::Cancelled, 'hold_expires_at' => null]);
            $this->ledger->release([$locked->id]);
            $this->payments()->expirePendingPayments($locked);
        });

        return new CancellationResult(CancellationOutcome::Cancelled);
    }

    public function request(Booking $booking, string $reason, ?int $userId = null): Refund
    {
        return DB::transaction(function () use ($booking, $reason, $userId) {
            $locked = $this->lockCancellablePaid($booking);
            $amount = $this->calculate($locked)->amount;

            if ($amount <= 0) {
                throw RefundException::nothingToRefund();
            }

            return $this->createRequest($locked, $amount, $reason, $userId);
        });
    }

    /**
     * Owner cancels a needs_review booking because the property could not honour it: the whole paid amount
     * is refunded and the policy tiers do not apply. Request and approval share one transaction, so the
     * booking never rests in an in-between state. The money leaving is still recorded through markPaid.
     */
    public function cancelForPropertyFault(Booking $booking, string $reason, User $actor): Refund
    {
        if (! $actor->is_active || ! $actor->can('approve_refund')) {
            throw BookingReviewException::notAuthorized();
        }

        return DB::transaction(function () use ($booking, $reason, $actor) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::NeedsReview) {
                throw BookingReviewException::notInReview();
            }

            if ($this->hasOpenRequest($locked)) {
                throw RefundException::alreadyRequested();
            }

            $refund = $this->createRequest($locked, $locked->paid_amount, $reason, $actor->id);
            $approved = $this->approveLocked($refund, $actor->id, [BookingStatus::NeedsReview]);

            $this->audit->record($locked, BookingAuditTrail::ACTION_REVIEW_CANCELLED_FULL_REFUND, $actor->id, [
                'refund_id' => $approved->id,
                'amount' => $approved->amount,
                'reason' => $reason,
            ]);

            return $approved;
        });
    }

    private function cancelPaid(Booking $booking, string $reason, ?int $userId): CancellationResult
    {
        return DB::transaction(function () use ($booking, $reason, $userId) {
            $locked = $this->lockCancellablePaid($booking);
            $amount = $this->calculate($locked)->amount;

            if ($amount > 0) {
                return new CancellationResult(
                    CancellationOutcome::RefundRequested,
                    $this->createRequest($locked, $amount, $reason, $userId),
                );
            }

            $locked->update([
                'status' => BookingStatus::Cancelled,
                'hold_expires_at' => null,
                'cancelled_at' => now(),
                'cancellation_note' => $reason,
            ]);
            $this->ledger->release([$locked->id]);
            $this->payments()->expirePendingPayments($locked);
            $this->audit->record($locked, BookingAuditTrail::ACTION_CANCELLED_WITHOUT_REFUND, $userId, [
                'reason' => $reason,
                'paid_amount' => $locked->paid_amount,
            ]);

            return new CancellationResult(CancellationOutcome::CancelledWithoutRefund);
        });
    }

    private function lockCancellablePaid(Booking $booking): Booking
    {
        $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

        if (! $this->holdsGuestMoney($locked) || ! $this->isCancellable($locked)) {
            throw RefundException::notCancellable();
        }

        if ($this->hasOpenRequest($locked)) {
            throw RefundException::alreadyRequested();
        }

        return $locked;
    }

    private function createRequest(Booking $booking, int $amount, string $reason, ?int $userId): Refund
    {
        return Refund::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => RefundStatus::Requested,
            'requested_by' => $userId,
        ]);
    }

    public function approve(Refund $refund, int $userId): Refund
    {
        return $this->approveLocked($refund, $userId, [BookingStatus::Paid, BookingStatus::PendingPayment]);
    }

    /**
     * @param  array<int, BookingStatus>  $allowedBookingStatuses
     */
    private function approveLocked(Refund $refund, int $userId, array $allowedBookingStatuses): Refund
    {
        return $this->transition($refund, RefundStatus::Approved, $allowedBookingStatuses, function (Refund $locked, Booking $booking) use ($userId) {
            if ($locked->amount <= 0 || $locked->amount > $booking->paid_amount) {
                throw RefundException::amountExceedsPaid();
            }

            $booking->update(['status' => BookingStatus::Refunded, 'hold_expires_at' => null]);
            $this->ledger->release([$booking->id]);
            $this->payments()->expirePendingPayments($booking);

            return ['approved_by' => $userId];
        });
    }

    public function reject(Refund $refund, int $userId, string $note): Refund
    {
        return $this->transition($refund, RefundStatus::Rejected, null, fn (Refund $locked) => [
            'approved_by' => $userId,
            'reason' => trim($locked->reason."\nCatatan penolakan: ".$note),
        ]);
    }

    public function markPaid(Refund $refund, int $userId): Refund
    {
        return $this->transition($refund, RefundStatus::Paid, [BookingStatus::Refunded], function (Refund $locked, Booking $booking) use ($userId) {
            $alreadyPaidOut = (int) $booking->payments()
                ->where('direction', PaymentDirection::Out)
                ->where('status', PaymentStatus::Paid)
                ->sum('amount');

            if ($locked->amount > $booking->paid_amount - $alreadyPaidOut) {
                throw RefundException::amountExceedsPaid();
            }

            // Ledger row only: booking.paid_amount stays the gross amount received from the customer.
            $booking->payments()->create([
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
     * @param  ?array<int, BookingStatus>  $allowedBookingStatuses  statuses the locked booking may have, null to skip the check
     * @param  callable(Refund, Booking): array<string, mixed>  $apply  side effects; returns extra columns to save
     */
    private function transition(Refund $refund, RefundStatus $to, ?array $allowedBookingStatuses, callable $apply): Refund
    {
        return DB::transaction(function () use ($refund, $to, $allowedBookingStatuses, $apply) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();

            if (! $this->canTransition($locked->status, $to)) {
                throw RefundException::invalidTransition($locked->status, $to);
            }

            if ($allowedBookingStatuses !== null && ! in_array($booking->status, $allowedBookingStatuses, true)) {
                throw RefundException::bookingNotRefundable($booking->status);
            }

            $locked->update(['status' => $to] + $apply($locked, $booking));

            return $locked;
        });
    }

    /**
     * Resolved lazily: PaymentService already depends on billing and the ledger, and only the
     * cancel paths need it here.
     */
    private function payments(): PaymentService
    {
        return app(PaymentService::class);
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
