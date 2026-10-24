<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Single place that answers "how much does this booking cost in total". Pre-orders are already
 * priced into booking.total at checkout; food ordered later and billed to the booking is added
 * here, so the invoice and the payment gateway always agree on the amount. No tax is charged
 * (client decision, 24 Oct 2026).
 */
class BookingBilling
{
    public function __construct(
        private readonly SettingRepository $settings,
    ) {}

    /**
     * @return array{subtotal: int, total: int}
     */
    public function breakdown(Booking $booking): array
    {
        $extraSubtotal = $this->extraFoodSubtotal($booking);

        return [
            'subtotal' => $booking->subtotal + $extraSubtotal,
            'total' => $booking->total + $extraSubtotal,
        ];
    }

    public function grandTotal(Booking $booking): int
    {
        return $this->breakdown($booking)['total'];
    }

    public function outstanding(Booking $booking): int
    {
        return max(0, $this->grandTotal($booking) - $booking->paid_amount);
    }

    /**
     * The first payment a guest may make instead of the full amount (FR-33). Null when the owner
     * has not enabled a down payment or the booking already received money, because a down payment
     * is only ever the opening payment. Rounded up to whole rupiah so the rest is never overstated.
     */
    public function downPaymentAmount(Booking $booking): ?int
    {
        $percent = $this->settings->downPaymentPercent();

        if ($percent <= 0 || $percent >= 100 || $booking->paid_amount > 0) {
            return null;
        }

        return (int) ceil($this->grandTotal($booking) * $percent / 100);
    }

    private function extraFoodSubtotal(Booking $booking): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.booking_id', $booking->id)
            ->where('orders.bill_to_booking', true)
            ->where('orders.source', '!=', Order::SOURCE_PREORDER)
            ->where('orders.status', '!=', OrderStatus::Dibatalkan->value)
            ->sum(DB::raw('order_items.price * order_items.qty'));
    }
}
