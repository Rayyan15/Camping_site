<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewReason;
use App\Enums\PaymentStatus;
use App\Events\BookingNeedsReview;
use App\Exceptions\InvalidPaymentAmountException;
use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\ManualPaymentException;
use App\Exceptions\PaymentException;
use App\Exceptions\UnitUnavailableException;
use App\Exceptions\UnknownPaymentException;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Unit;
use App\Services\BookingBilling;
use App\Services\UnitNightLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /** Statuses whose units are already secured, so a partial payment is just a payment. */
    private const UNITS_SECURED_STATUSES = [
        BookingStatus::Paid, BookingStatus::CheckedIn, BookingStatus::CheckedOut, BookingStatus::NeedsReview,
    ];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly UnitNightLedger $ledger,
        private readonly BookingBilling $billing,
    ) {}

    /** QR orders are paid on the guest's phone, so the hosted page offers QR and e-wallet methods only. */
    private const ORDER_ENABLED_PAYMENTS = ['other_qris', 'gopay', 'shopeepay'];

    /**
     * Opens a gateway payment for whatever is still owed on the booking, or for the down payment
     * when the guest chose it. A still-valid pending payment for the same amount is reused, so
     * repeated clicks do not spawn parallel payment links.
     *
     * @throws PaymentException
     */
    public function initiate(Booking $booking, bool $downPayment = false): PaymentIntent
    {
        $payment = DB::transaction(fn () => $this->createPendingPayment($booking, $downPayment));
        $booking->loadMissing('customer');

        return $this->openHostedPage($payment, new GatewayCharge(
            reference: (string) $payment->gateway_ref,
            amount: $payment->amount,
            label: ($downPayment ? 'DP booking ' : 'Booking ').$booking->code,
            customer: [
                'name' => $booking->customer?->name,
                'phone' => $booking->customer?->phone,
                'email' => $booking->customer?->email,
            ],
            finishUrl: route('booking.status', $booking->access_token),
        ));
    }

    /**
     * Opens a QRIS or e-wallet payment for a QR order the guest chose to pay online (FR-47).
     *
     * @throws PaymentException
     */
    public function initiateForOrder(Order $order): PaymentIntent
    {
        $payment = DB::transaction(fn () => $this->createPendingOrderPayment($order));
        $order->loadMissing('diningSpot');

        return $this->openHostedPage($payment, new GatewayCharge(
            reference: (string) $payment->gateway_ref,
            amount: $payment->amount,
            label: 'Pesanan '.$order->code,
            customer: ['name' => $order->customer_name, 'phone' => $order->customer_phone],
            finishUrl: route('qr.track', [$order->diningSpot->qr_token, $order->code]),
            enabledPayments: self::ORDER_ENABLED_PAYMENTS,
        ));
    }

    /**
     * @throws PaymentException
     */
    private function openHostedPage(Payment $payment, GatewayCharge $charge): PaymentIntent
    {
        if ($payment->redirect_url !== null) {
            return new PaymentIntent($payment->redirect_url, $payment->gateway_ref);
        }

        try {
            $redirectUrl = $this->gateway->createTransaction($charge);
        } catch (PaymentException $e) {
            // A timeout says nothing about whether the gateway accepted the order, so the reason is
            // kept: a later settlement for this reference must still be honoured.
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $e->isNetworkFailure() ? PaymentFailureReason::GatewayUnreachable : null,
            ]);
            Log::error('Payment gateway transaction failed', ['reference' => $payment->gateway_ref, 'error' => $e->getMessage()]);

            throw $e;
        }

        $payment->update(['redirect_url' => $redirectUrl]);

        return new PaymentIntent($redirectUrl, $payment->gateway_ref);
    }

    /**
     * Closes the booking's open gateway links so the hosted pages stop accepting money for it.
     * Called inside the transaction that cancels or refunds the booking.
     */
    public function expirePendingPayments(Booking $booking): void
    {
        $booking->payments()
            ->where('method', PaymentMethod::Gateway)
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Expired]);
    }

    /**
     * Applies a gateway notification. Safe to call repeatedly with the same notification.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidPaymentSignatureException
     * @throws UnknownPaymentException
     */
    public function handleNotification(array $payload): void
    {
        if (! $this->gateway->isAuthentic($payload)) {
            Log::warning('Payment notification rejected: bad signature', ['order_id' => $payload['order_id'] ?? null]);

            throw InvalidPaymentSignatureException::make();
        }

        $reference = (string) ($payload['order_id'] ?? '');

        DB::transaction(function () use ($payload, $reference) {
            $payment = Payment::where('gateway_ref', $reference)->lockForUpdate()->first()
                ?? throw UnknownPaymentException::forReference($reference);

            $this->assertAmountMatches($payload, $payment);

            // A settled or closed payment never changes again, which makes retries harmless. The exceptions
            // are a payment we failed only because the gateway never answered, and a link we expired on our
            // side (booking cancelled) that the guest still paid: that money is real and must be recorded.
            $recoverable = $payment->isNetworkFailure() || $payment->status === PaymentStatus::Expired;

            if ($payment->status !== PaymentStatus::Pending && ! $recoverable) {
                return;
            }

            $status = $this->gateway->statusFrom($payload);

            if ($status === PaymentStatus::Pending || ($recoverable && $status !== PaymentStatus::Paid)) {
                return;
            }

            $payment->forceFill([
                'status' => $status,
                'failure_reason' => null,
                'raw_payload' => $payload,
                'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            ])->save();

            if ($status === PaymentStatus::Paid) {
                $this->settle($payment);
            }

            Log::info('Payment notification processed', ['reference' => $reference, 'status' => $status->value]);
        });
    }

    /**
     * Midtrans reports gross_amount as "150000.00". Notifications without it (the dev fake
     * gateway) carry no amount to compare, so only a present value is checked.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidPaymentAmountException
     */
    private function assertAmountMatches(array $payload, Payment $payment): void
    {
        if (! array_key_exists('gross_amount', $payload)) {
            return;
        }

        $gross = (string) $payload['gross_amount'];

        if (! preg_match('/^\d+(\.0+)?$/', $gross) || (int) $gross !== $payment->amount) {
            Log::warning('Payment notification rejected: amount mismatch', [
                'reference' => $payment->gateway_ref,
                'expected' => $payment->amount,
                'received' => $gross,
            ]);

            throw InvalidPaymentAmountException::forReference((string) $payment->gateway_ref);
        }
    }

    /**
     * Records money received outside the gateway (cash or bank transfer) for FR-32.
     *
     * A down payment on a pending booking extends its unit hold to booking.manual_dp_hold_minutes
     * from now, so staff and guest have time to settle the rest instead of the hold lapsing at the
     * short online-checkout deadline.
     *
     * @throws ManualPaymentException
     */
    public function recordManual(Booking $booking, int $amount, string $method, ?string $proofPath, int $userId): Payment
    {
        $paymentMethod = PaymentMethod::from($method);

        return DB::transaction(function () use ($booking, $amount, $paymentMethod, $proofPath, $userId) {
            $locked = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            $this->assertManualPaymentAllowed($locked, $amount);

            $payment = $locked->payments()->create([
                'direction' => PaymentDirection::In,
                'method' => $paymentMethod,
                'amount' => $amount,
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'proof_path' => $proofPath,
                'recorded_by' => $userId,
            ]);

            $this->extendHoldForDownPayment($locked, $amount);
            $this->applyToBooking($locked, $amount);
            $booking->refresh();

            return $payment;
        });
    }

    /**
     * @throws ManualPaymentException
     */
    private function assertManualPaymentAllowed(Booking $booking, int $amount): void
    {
        if ($amount <= 0) {
            throw ManualPaymentException::invalidAmount();
        }

        $outstanding = $this->billing->outstanding($booking);

        if ($amount > $outstanding) {
            throw ManualPaymentException::exceedsOutstanding($outstanding);
        }

        $label = $booking->status->getLabel();

        if ($booking->status === BookingStatus::Refunded) {
            throw ManualPaymentException::bookingNotPayable($label);
        }

        // A booking without a live hold (expired, cancelled, lapsed) can only be revived by a payment
        // that covers it entirely; a down payment would leave money on a booking holding no units.
        $securesUnits = in_array($booking->status, self::UNITS_SECURED_STATUSES, true) || $booking->isHoldActive();

        if (! $securesUnits && $amount < $outstanding) {
            throw ManualPaymentException::partialNotAllowed($label);
        }
    }

    private function extendHoldForDownPayment(Booking $booking, int $amount): void
    {
        $isDownPayment = $booking->paid_amount + $amount < $this->billing->grandTotal($booking);

        if (! $isDownPayment || ! $booking->isHoldActive()) {
            return;
        }

        // A down payment keeps the units at least until the end of the check-in day, where the rest is settled.
        $extended = now()->addMinutes((int) config('booking.manual_dp_hold_minutes'))
            ->max($booking->check_in->copy()->setTime(23, 59, 59));

        if ($extended->greaterThan($booking->hold_expires_at)) {
            $booking->hold_expires_at = $extended;
        }
    }

    /**
     * @throws PaymentException
     */
    private function createPendingPayment(Booking $booking, bool $downPayment): Payment
    {
        $locked = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
        $outstanding = $this->billing->outstanding($locked);

        if ($outstanding <= 0) {
            throw PaymentException::nothingToPay();
        }

        $amount = $outstanding;

        if ($downPayment) {
            $amount = $this->billing->downPaymentAmount($locked) ?? throw PaymentException::downPaymentUnavailable();
        }

        // A link for an amount that no longer matches (food billed since, or the other payment plan) is
        // closed, so the guest cannot pay two links. Money that still arrives on it is recorded as overpaid.
        $locked->payments()
            ->where('method', PaymentMethod::Gateway)
            ->where('status', PaymentStatus::Pending)
            ->where('amount', '!=', $amount)
            ->update(['status' => PaymentStatus::Expired]);

        return $this->reusableOrNewGatewayPayment($locked, $locked->code, $amount);
    }

    /**
     * @throws PaymentException
     */
    private function createPendingOrderPayment(Order $order): Payment
    {
        $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->isPaid() || $locked->bill_to_booking) {
            throw PaymentException::nothingToPay();
        }

        return $this->reusableOrNewGatewayPayment($locked, $locked->code, $locked->total);
    }

    private function reusableOrNewGatewayPayment(Booking|Order $payable, string $code, int $amount): Payment
    {
        $reusable = $payable->payments()
            ->where('method', PaymentMethod::Gateway)
            ->where('status', PaymentStatus::Pending)
            ->where('amount', $amount)
            ->whereNotNull('redirect_url')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($reusable) {
            return $reusable;
        }

        $attempt = $payable->payments()->where('method', PaymentMethod::Gateway)->count() + 1;

        return $payable->payments()->create([
            'direction' => PaymentDirection::In,
            'method' => PaymentMethod::Gateway,
            'gateway_ref' => $code.'-'.$attempt,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addMinutes((int) config('booking.hold_minutes')),
        ]);
    }

    /**
     * Applies a paid gateway payment. Money beyond what the booking still owes (a second link paid
     * after the first) stays on the payment, flagged for refund, and is never added to paid_amount.
     */
    private function settle(Payment $payment): void
    {
        if ($payment->payable_type === (new Order)->getMorphClass()) {
            $this->settleOrder($payment);

            return;
        }

        $booking = $payment->payable()->lockForUpdate()->firstOrFail();

        // A cancellation is final: an old link paid afterwards must not bring the booking back.
        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
            $payment->forceFill(['review_reason' => PaymentReviewReason::BookingClosed])->save();
            Log::warning('Payment arrived for a closed booking and needs a refund', [
                'reference' => $payment->gateway_ref,
                'booking' => $booking->code,
            ]);

            return;
        }

        $this->extendHoldForDownPayment($booking, $payment->amount);
        $excess = $this->applyToBooking($booking, $payment->amount);

        if ($excess === 0) {
            return;
        }

        $payment->forceFill(['review_reason' => PaymentReviewReason::Overpaid])->save();

        Log::warning('Payment exceeds the booking balance and needs a refund', [
            'reference' => $payment->gateway_ref,
            'booking' => $booking->code,
            'excess' => $excess,
        ]);
    }

    /**
     * A QR order paid online becomes visible to the kitchen once marked paid. A second paid link for
     * an order already settled at the cashier is kept and flagged for refund.
     */
    private function settleOrder(Payment $payment): void
    {
        $order = Order::whereKey($payment->payable_id)->lockForUpdate()->firstOrFail();

        if ($order->isPaid()) {
            $payment->forceFill(['review_reason' => PaymentReviewReason::Overpaid])->save();
            Log::warning('Online payment for an order that was already paid', ['reference' => $payment->gateway_ref]);

            return;
        }

        $order->update(['payment_status' => Order::PAYMENT_PAID]);
    }

    /**
     * Adds paid money to the booking and moves it to paid once it is covered (PRD 5.3 point 4).
     * Called inside a transaction with the booking row locked.
     *
     * @return int the part of $amount that exceeded the balance and was not applied
     */
    private function applyToBooking(Booking $booking, int $amount): int
    {
        $due = $this->billing->grandTotal($booking);
        $applied = min($amount, max(0, $due - $booking->paid_amount));

        $booking->paid_amount += $applied;

        $enteredReview = false;

        if ($booking->paid_amount >= $due) {
            $booking->status = $this->statusForFullPayment($booking);

            if ($booking->status === BookingStatus::NeedsReview) {
                $enteredReview = $booking->review_started_at === null;
                $booking->review_started_at ??= now();
            }
        }

        $booking->save();

        if ($enteredReview) {
            BookingNeedsReview::dispatch($booking);
        }

        return $amount - $applied;
    }

    private function statusForFullPayment(Booking $booking): BookingStatus
    {
        $holdStillValid = $booking->status === BookingStatus::PendingPayment
            && $booking->hold_expires_at?->isFuture();

        if ($holdStillValid) {
            return BookingStatus::Paid;
        }

        $canBeRevived = in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Expired], true);

        if (! $canBeRevived) {
            return $booking->status;
        }

        if ($this->unitsStillFree($booking) && $this->reclaimUnits($booking)) {
            return BookingStatus::Paid;
        }

        Log::warning('Late payment on a booking whose units are gone', ['booking' => $booking->code]);

        return BookingStatus::NeedsReview;
    }

    /**
     * Takes the nights back in the ledger for a revived booking. A conflict here means the free check
     * raced with another claim, so the booking goes to manual review instead of failing the notification.
     */
    private function reclaimUnits(Booking $booking): bool
    {
        try {
            // Savepoint: a conflict on a later line must not leave the earlier lines' nights claimed.
            DB::transaction(fn () => $this->ledger->claim($booking->bookingUnits()->get()));
        } catch (UnitUnavailableException) {
            return false;
        }

        return true;
    }

    /**
     * Whether every unit of a lapsed booking is still free for its stay. Locks the unit rows so
     * a concurrent createBooking cannot take them while we decide.
     */
    private function unitsStillFree(Booking $booking): bool
    {
        $lines = $booking->bookingUnits()->get();

        Unit::whereIn('id', $lines->pluck('unit_id'))->lockForUpdate()->get();

        return $lines->every(fn (BookingUnit $line) => $line->unit->isFreeBetween(
            $line->check_in->toDateString(),
            $line->check_out->toDateString(),
            $booking->id,
        ));
    }
}
