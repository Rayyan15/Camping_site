<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QrPaymentChoice;
use App\Events\OrderPlaced;
use App\Exceptions\TransferProofException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\TransferProofSubmittedNotification;
use App\Support\BankAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Transfer proofs for QR food orders. Unlike a booking there is no hold to manage: the order simply
 * stays out of the kitchen queue until the cashier confirms the money arrived.
 */
class OrderTransferProofService
{
    private const REVIEW_PERMISSION = 'review_order_transfer';

    /**
     * @throws TransferProofException
     */
    public function submit(Order $order, UploadedFile $file): Payment
    {
        if (! BankAccount::current()->isComplete()) {
            throw TransferProofException::bankAccountNotSet();
        }

        $path = (string) $file->store(TransferProofService::DIRECTORY, TransferProofService::DISK);

        try {
            $payment = DB::transaction(function () use ($order, $path) {
                $locked = $this->lockAwaitingTransfer($order);

                // A new upload replaces a proof still waiting, so the cashier only ever reviews one.
                $locked->payments()->where('status', PaymentStatus::Pending->value)->update(['status' => PaymentStatus::Expired->value]);

                return $locked->payments()->create([
                    'direction' => PaymentDirection::In,
                    'method' => PaymentMethod::Transfer,
                    'amount' => $locked->total,
                    'status' => PaymentStatus::Pending,
                    'proof_path' => $path,
                ]);
            });
        } catch (Throwable $e) {
            Storage::disk(TransferProofService::DISK)->delete($path);

            throw $e;
        }

        $reviewers = User::where('is_active', true)->get()->filter(fn (User $user) => $user->can(self::REVIEW_PERMISSION));
        Notification::send($reviewers, new TransferProofSubmittedNotification($payment));

        return $payment;
    }

    /**
     * @throws TransferProofException
     */
    public function confirm(Payment $payment, int $userId): Payment
    {
        $order = null;

        $confirmed = DB::transaction(function () use ($payment, $userId, &$order) {
            $locked = $this->lockReviewable($payment);
            $order = Order::whereKey($locked->payable_id)->lockForUpdate()->firstOrFail();

            if ($order->statusEnum() === OrderStatus::Dibatalkan) {
                throw TransferProofException::orderClosed();
            }

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'recorded_by' => $userId,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ])->save();

            $order->update(['payment_status' => Order::PAYMENT_PAID]);

            return $locked;
        });

        OrderPlaced::dispatch($order);

        return $confirmed;
    }

    /**
     * @throws TransferProofException
     */
    public function reject(Payment $payment, string $reason, int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $userId) {
            $locked = $this->lockReviewable($payment);

            $locked->forceFill([
                'status' => PaymentStatus::Failed,
                'rejection_reason' => $reason,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ])->save();

            return $locked;
        });
    }

    /** The proof the guest should see on the tracking page: waiting or refused. Null once paid. */
    public function latestProof(Order $order): ?Payment
    {
        if ($order->isPaid()) {
            return null;
        }

        return $order->payments()
            ->whereNotNull('proof_path')
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->latest('id')
            ->first();
    }

    /**
     * @throws TransferProofException
     */
    private function lockAwaitingTransfer(Order $order): Order
    {
        $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

        $awaiting = $locked->payment_choice === QrPaymentChoice::Transfer
            && ! $locked->isPaid()
            && $locked->statusEnum() !== OrderStatus::Dibatalkan;

        return $awaiting ? $locked : throw TransferProofException::orderNotAwaitingTransfer();
    }

    /**
     * @throws TransferProofException
     */
    private function lockReviewable(Payment $payment): Payment
    {
        $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

        $awaitingReview = $locked->payable_type === (new Order)->getMorphClass()
            && $locked->method === PaymentMethod::Transfer
            && $locked->status === PaymentStatus::Pending
            && filled($locked->proof_path);

        return $awaitingReview ? $locked : throw TransferProofException::notAwaitingReview();
    }
}
