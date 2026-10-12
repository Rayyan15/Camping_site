<?php

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewReason;
use App\Enums\PaymentStatus;
use App\Events\TransferProofSubmitted;
use App\Exceptions\PaymentException;
use App\Exceptions\TransferProofException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingBilling;
use App\Services\GuestMailer;
use App\Support\BankAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Manual bank transfer flow: the guest uploads a proof, staff confirm or reject it.
 * The amount always comes from the server; the guest only picks the file (and, if enabled, the plan).
 */
class TransferProofService
{
    public const DISK = 'local';

    public const DIRECTORY = 'payment-proofs';

    public function __construct(
        private readonly BookingBilling $billing,
        private readonly PaymentService $payments,
        private readonly GuestMailer $mailer,
    ) {}

    /**
     * Stores the proof and opens a pending transfer payment for what the booking still owes (or the
     * down payment). A newer proof replaces the pending one. The unit hold is extended so the tent is
     * not released while staff check the bank statement.
     *
     * @throws PaymentException
     */
    public function submit(Booking $booking, UploadedFile $file, bool $downPayment = false): Payment
    {
        if (! BankAccount::current()->isComplete()) {
            throw TransferProofException::bankAccountNotSet();
        }

        $path = (string) $file->store(self::DIRECTORY, self::DISK);

        try {
            $payment = DB::transaction(fn () => $this->recordProof($booking, $path, $downPayment));
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }

        $this->mailer->proofReceived($booking->loadMissing('customer'));

        return $payment;
    }

    /**
     * @throws PaymentException
     */
    public function confirm(Payment $payment, int $userId): Payment
    {
        $booking = null;

        $confirmed = DB::transaction(function () use ($payment, $userId, &$booking) {
            $locked = $this->lockReviewable($payment);
            $booking = Booking::whereKey($locked->payable_id)->lockForUpdate()->firstOrFail();

            if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
                throw TransferProofException::bookingClosed($booking->status->getLabel());
            }

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'recorded_by' => $userId,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ])->save();

            $excess = $this->payments->applyConfirmedTransfer($booking, $locked->amount);

            if ($excess > 0) {
                $locked->forceFill(['review_reason' => PaymentReviewReason::Overpaid])->save();
                Log::warning('Confirmed transfer exceeds the booking balance and needs a refund', [
                    'booking' => $booking->code,
                    'excess' => $excess,
                ]);
            }

            return $locked;
        });

        $this->mailer->paymentConfirmed($booking->loadMissing('customer'));

        return $confirmed;
    }

    /**
     * Refuses a proof. The guest can upload again; the hold is cut back to the normal checkout
     * window so a rejected proof does not keep the tent for a whole review period.
     *
     * @throws PaymentException
     */
    public function reject(Payment $payment, string $reason, int $userId): Payment
    {
        $booking = null;

        $rejected = DB::transaction(function () use ($payment, $reason, $userId, &$booking) {
            $locked = $this->lockReviewable($payment);
            $booking = Booking::whereKey($locked->payable_id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => PaymentStatus::Failed,
                'rejection_reason' => $reason,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ])->save();

            $this->shortenHoldAfterRejection($booking);

            return $locked;
        });

        $this->mailer->proofRejected($booking->loadMissing('customer'), $reason);

        return $rejected;
    }

    /**
     * The proof the guest should hear about: still waiting, or refused with a reason. Null when the
     * latest transfer proof was confirmed, replaced, or none was ever sent.
     */
    public function latestProof(Booking $booking): ?Payment
    {
        $latest = $booking->payments()
            ->where('method', PaymentMethod::Transfer)
            ->whereNotNull('proof_path')
            ->where('status', '!=', PaymentStatus::Expired)
            ->latest('id')
            ->first();

        $needsAttention = $latest?->status === PaymentStatus::Pending
            || ($latest?->status === PaymentStatus::Failed && filled($latest->rejection_reason));

        return $needsAttention ? $latest : null;
    }

    /**
     * @throws PaymentException
     */
    private function recordProof(Booking $booking, string $path, bool $downPayment): Payment
    {
        $locked = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->isHoldActive()) {
            throw TransferProofException::holdLapsed();
        }

        $outstanding = $this->billing->outstanding($locked);

        if ($outstanding <= 0) {
            throw PaymentException::nothingToPay();
        }

        $amount = $downPayment
            ? ($this->billing->downPaymentAmount($locked) ?? throw PaymentException::downPaymentUnavailable())
            : $outstanding;

        $locked->payments()->awaitingProofReview()->update(['status' => PaymentStatus::Expired]);

        $payment = $locked->payments()->create([
            'direction' => PaymentDirection::In,
            'method' => PaymentMethod::Transfer,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'proof_path' => $path,
        ]);

        $this->extendHoldForReview($locked);
        TransferProofSubmitted::dispatch($payment);

        return $payment;
    }

    private function extendHoldForReview(Booking $booking): void
    {
        $until = now()->addHours((int) config('booking.proof_review_hours'));

        if ($until->greaterThan($booking->hold_expires_at)) {
            $booking->update(['hold_expires_at' => $until]);
        }
    }

    private function shortenHoldAfterRejection(Booking $booking): void
    {
        if (! $booking->isHoldActive()) {
            return;
        }

        $until = now()->addMinutes((int) config('booking.hold_minutes'));

        if ($until->lessThan($booking->hold_expires_at)) {
            $booking->update(['hold_expires_at' => $until]);
        }
    }

    /**
     * @throws TransferProofException
     */
    private function lockReviewable(Payment $payment): Payment
    {
        $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

        $awaitingReview = $locked->payable_type === (new Booking)->getMorphClass()
            && $locked->method === PaymentMethod::Transfer
            && $locked->status === PaymentStatus::Pending
            && filled($locked->proof_path);

        return $awaitingReview ? $locked : throw TransferProofException::notAwaitingReview();
    }
}
