<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\QrPaymentChoice;
use App\Models\Order;
use App\Services\SettingRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Cancels QR transfer orders the guest never paid, so their portions return to stock. Orders with a
 * proof waiting for the kitchen are left alone.
 */
class CancelUnpaidTransferOrders implements ShouldQueue
{
    use Queueable;

    public function handle(SettingRepository $settings): void
    {
        $minutes = $settings->qrTransferCancelMinutes();
        if ($minutes === 0) {
            return;
        }

        $orders = Order::where('payment_choice', QrPaymentChoice::Transfer)
            ->where('payment_status', Order::PAYMENT_UNPAID)
            ->where('status', OrderStatus::Baru->value)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->whereDoesntHave('payments', fn ($payments) => $payments->where('status', PaymentStatus::Pending))
            ->get();

        // One by one through the model, so the activity log shows who cancelled: the system.
        $orders->each->update(['status' => OrderStatus::Dibatalkan->value, 'auto_cancelled_at' => now()]);
        $cancelled = $orders->count();

        if ($cancelled > 0) {
            Log::info('Cancelled unpaid QR transfer orders', ['count' => $cancelled, 'after_minutes' => $minutes]);
        }
    }
}
