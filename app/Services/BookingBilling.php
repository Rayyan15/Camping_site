<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single place that answers "how much does this booking cost in total". Pre-orders are already
 * priced into booking.total at checkout; food ordered later and billed to the booking is added
 * here with tax, so the invoice and the payment gateway always agree on the amount.
 */
class BookingBilling
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly SettingRepository $settings,
    ) {}

    /**
     * @return array{subtotal: int, tax: int, total: int}
     */
    public function breakdown(Booking $booking): array
    {
        $extraSubtotal = $this->extraFoodSubtotal($booking);
        $extraTax = $this->extraFoodTax($booking);

        return [
            'subtotal' => $booking->subtotal + $extraSubtotal,
            'tax' => $booking->tax + $extraTax,
            'total' => $booking->total + $extraSubtotal + $extraTax,
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

    /**
     * Tax stored on each order when it was billed, so changing the rate later never rewrites an old bill.
     * Orders from before the column existed fall back to the current rate.
     */
    private function extraFoodTax(Booking $booking): int
    {
        return (int) $this->extraFoodOrders($booking)
            ->get(['total', 'tax'])
            ->sum(fn (Order $order) => $order->tax ?? $this->pricing->taxFor($order->total));
    }

    /**
     * @return Builder<Order>
     */
    private function extraFoodOrders(Booking $booking): Builder
    {
        return Order::query()
            ->where('booking_id', $booking->id)
            ->where('bill_to_booking', true)
            ->where('source', '!=', Order::SOURCE_PREORDER);
    }

    private function extraFoodSubtotal(Booking $booking): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.booking_id', $booking->id)
            ->where('orders.bill_to_booking', true)
            ->where('orders.source', '!=', Order::SOURCE_PREORDER)
            ->sum(DB::raw('order_items.price * order_items.qty'));
    }
}
