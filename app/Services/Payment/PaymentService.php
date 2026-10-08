<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\PaymentException;
use App\Exceptions\UnknownPaymentException;
use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Opens a gateway payment for whatever is still owed on the booking.
     *
     * @throws PaymentException
     */
    public function initiate(Booking $booking): PaymentIntent
    {
        $payment = DB::transaction(fn () => $this->createPendingPayment($booking));

        try {
            $redirectUrl = $this->gateway->createTransaction($booking, $payment->gateway_ref, $payment->amount);
        } catch (PaymentException $e) {
            $payment->update(['status' => PaymentStatus::Failed]);
            Log::error('Payment gateway transaction failed', ['reference' => $payment->gateway_ref, 'error' => $e->getMessage()]);

            throw $e;
        }

        return new PaymentIntent($redirectUrl, $payment->gateway_ref);
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

            // A settled or closed payment never changes again, which makes retries harmless.
            if ($payment->status !== PaymentStatus::Pending) {
                return;
            }

            $status = $this->gateway->statusFrom($payload);

            if ($status === PaymentStatus::Pending) {
                return;
            }

            $payment->forceFill([
                'status' => $status,
                'raw_payload' => $payload,
                'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            ])->save();

            if ($status === PaymentStatus::Paid) {
                $this->applyToBooking($payment->payable()->lockForUpdate()->firstOrFail(), $payment->amount);
            }

            Log::info('Payment notification processed', ['reference' => $reference, 'status' => $status->value]);
        });
    }

    /**
     * Records money received outside the gateway (cash or bank transfer) for FR-32.
     */
    public function recordManual(Booking $booking, int $amount, string $method, ?string $proofPath, int $userId): Payment
    {
        $paymentMethod = PaymentMethod::from($method);

        return DB::transaction(function () use ($booking, $amount, $paymentMethod, $proofPath, $userId) {
            $locked = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            $payment = $locked->payments()->create([
                'direction' => PaymentDirection::In,
                'method' => $paymentMethod,
                'amount' => $amount,
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'proof_path' => $proofPath,
                'recorded_by' => $userId,
            ]);

            $this->applyToBooking($locked, $amount);
            $booking->refresh();

            return $payment;
        });
    }

    private function createPendingPayment(Booking $booking): Payment
    {
        $locked = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
        $outstanding = $locked->total - $locked->paid_amount;

        if ($outstanding <= 0) {
            throw PaymentException::nothingToPay();
        }

        $attempt = $locked->payments()->where('method', PaymentMethod::Gateway)->count() + 1;

        return $locked->payments()->create([
            'direction' => PaymentDirection::In,
            'method' => PaymentMethod::Gateway,
            'gateway_ref' => $locked->code.'-'.$attempt,
            'amount' => $outstanding,
            'status' => PaymentStatus::Pending,
        ]);
    }

    /**
     * Adds paid money to the booking and moves it to paid once it is covered (PRD 5.3 point 4).
     * Called inside a transaction with the booking row locked.
     */
    private function applyToBooking(Booking $booking, int $amount): void
    {
        $booking->paid_amount += $amount;

        if ($booking->paid_amount >= $booking->total) {
            $booking->status = $this->statusForFullPayment($booking);
        }

        $booking->save();
    }

    private function statusForFullPayment(Booking $booking): BookingStatus
    {
        $holdStillValid = $booking->status === BookingStatus::PendingPayment
            && $booking->hold_expires_at?->isFuture();

        if ($holdStillValid) {
            return BookingStatus::Paid;
        }

        $canBeRevived = in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Expired, BookingStatus::Cancelled], true);

        if (! $canBeRevived) {
            return $booking->status;
        }

        if ($this->unitsStillFree($booking)) {
            return BookingStatus::Paid;
        }

        Log::warning('Late payment on a booking whose units are gone', ['booking' => $booking->code]);

        return BookingStatus::NeedsReview;
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
