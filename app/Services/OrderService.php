<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\MenuItemUnavailableException;
use App\Exceptions\OrderBillingException;
use App\Models\DiningSpot;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class OrderService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_SUFFIX_LENGTH = 6;

    public function __construct(
        private readonly PricingService $pricing,
        private readonly DiningSpotService $diningSpots,
    ) {}

    /**
     * Creates an order (QR, walk-in or pre-order) priced from the menu table.
     * Pre-orders and orders with a booking are billed to that booking unless told otherwise.
     *
     * @param  array<int, array{menu_item_id: int, qty: int, notes?: string|null}>  $items
     *
     * @throws MenuItemUnavailableException
     */
    public function createOrder(
        string $source,
        array $items,
        ?int $bookingId = null,
        ?int $diningSpotId = null,
        ?CarbonInterface $scheduledAt = null,
        ?string $customerName = null,
        ?string $customerPhone = null,
        ?bool $billToBooking = null,
    ): Order {
        $lines = $this->pricing->menuLines($items);
        $billToBooking ??= $source === Order::SOURCE_PREORDER || $bookingId !== null;

        return DB::transaction(function () use ($source, $lines, $bookingId, $diningSpotId, $scheduledAt, $customerName, $customerPhone, $billToBooking) {
            $order = Order::create([
                'code' => $this->generateCode(),
                'source' => $source,
                'booking_id' => $bookingId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'dining_spot_id' => $diningSpotId,
                'scheduled_at' => $scheduledAt,
                'status' => OrderStatus::Baru->value,
                'total' => array_sum(array_column($lines, 'subtotal')),
                'payment_status' => Order::PAYMENT_UNPAID,
                'bill_to_booking' => $billToBooking,
            ]);

            $order->items()->createMany(array_map(fn (array $line) => [
                'menu_item_id' => $line['menu_item_id'],
                'qty' => $line['qty'],
                'price' => $line['price'],
                'notes' => $line['notes'],
            ], $lines));

            return $order;
        });
    }

    /**
     * Order placed by a guest scanning the QR of a table or tent. The booking is resolved from the
     * spot on the server, never taken from the request.
     *
     * @param  array<int, array{menu_item_id: int, qty: int, notes?: string|null}>  $items
     *
     * @throws MenuItemUnavailableException
     * @throws OrderBillingException
     */
    public function createQrOrder(
        DiningSpot $spot,
        array $items,
        string $customerName,
        ?string $customerPhone,
        bool $billToBooking,
    ): Order {
        $booking = $this->diningSpots->activeBooking($spot);

        if ($billToBooking && $booking === null) {
            throw OrderBillingException::noActiveBooking();
        }

        return $this->createOrder(
            Order::SOURCE_QR,
            $items,
            $billToBooking ? $booking->id : null,
            $spot->id,
            null,
            $customerName,
            $customerPhone,
            $billToBooking,
        );
    }

    /**
     * Order typed in by the cashier for a walk-in guest, optionally paid on the spot.
     *
     * @param  array<int, array{menu_item_id: int, qty: int, notes?: string|null}>  $items
     *
     * @throws MenuItemUnavailableException
     */
    public function createWalkinOrder(
        array $items,
        string $customerName,
        ?int $diningSpotId,
        ?PaymentMethod $paidWith,
        int $userId,
    ): Order {
        return DB::transaction(function () use ($items, $customerName, $diningSpotId, $paidWith, $userId) {
            $order = $this->createOrder(
                Order::SOURCE_WALKIN,
                $items,
                null,
                $diningSpotId,
                null,
                $customerName,
                null,
                false,
            );

            if ($paidWith !== null) {
                $this->markPaidAtCashier($order, $paidWith, $userId);
            }

            return $order;
        });
    }

    /**
     * Moves an order exactly one step forward in the kitchen queue.
     *
     * @throws InvalidOrderTransitionException
     */
    public function updateStatus(Order $order, OrderStatus $newStatus): Order
    {
        $current = OrderStatus::from($order->status);

        if (! $current->canMoveTo($newStatus)) {
            throw InvalidOrderTransitionException::between($current, $newStatus);
        }

        $order->update(['status' => $newStatus->value]);

        return $order;
    }

    /**
     * Records money taken at the cashier. Orders billed to a booking are settled with the booking instead.
     *
     * @throws OrderBillingException
     */
    public function markPaidAtCashier(Order $order, PaymentMethod $method, int $userId): Payment
    {
        return DB::transaction(function () use ($order, $method, $userId) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isPaid() || $locked->bill_to_booking) {
                throw OrderBillingException::alreadySettled();
            }

            $payment = $locked->payments()->create([
                'direction' => PaymentDirection::In,
                'method' => $method,
                'amount' => $locked->total,
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'recorded_by' => $userId,
            ]);

            $locked->update(['payment_status' => Order::PAYMENT_PAID]);
            $order->refresh();

            return $payment;
        });
    }

    /**
     * Public code with a random suffix so one guest cannot guess another guest's order.
     */
    private function generateCode(): string
    {
        do {
            $code = 'ORD-'.now()->format('ymd').'-'.$this->randomSuffix();
        } while (Order::where('code', $code)->exists());

        return $code;
    }

    private function randomSuffix(): string
    {
        $suffix = '';

        foreach (str_split(random_bytes(self::CODE_SUFFIX_LENGTH)) as $byte) {
            // The alphabet has 32 symbols, so the low five bits map evenly.
            $suffix .= self::CODE_ALPHABET[ord($byte) & 31];
        }

        return $suffix;
    }
}
