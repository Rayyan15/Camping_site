<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Everything sold for one day in one list, so front office and the owner can check that each
 * stay and each plate was billed and paid: tents in house that night, with their add-ons and
 * food, plus food sold to guests without a stay.
 */
class DailyOrdersService
{
    public const TENT = 'tent';

    public const TENT_ADDON = 'tent_addon';

    public const TENT_FOOD = 'tent_food';

    public const TENT_ADDON_FOOD = 'tent_addon_food';

    public const FOOD = 'food';

    public const LABELS = [
        self::TENT => 'Tenda saja',
        self::TENT_ADDON => 'Tenda + add-on',
        self::TENT_FOOD => 'Tenda + makanan',
        self::TENT_ADDON_FOOD => 'Tenda + add-on + makanan',
        self::FOOD => 'Makanan saja',
    ];

    private const DEAD = [BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::Refunded];

    public function __construct(private readonly BookingBilling $billing) {}

    /**
     * @return list<array{kind: string, code: string, name: string, tents: string, details: list<string>, category: string, bill: int, paid: int, owed: int, status: string}>
     */
    public function forDate(CarbonImmutable $date): array
    {
        return [...$this->stays($date), ...$this->foodOnly($date)];
    }

    /** @return list<array<string, mixed>> */
    private function stays(CarbonImmutable $date): array
    {
        return Booking::with(['customer', 'addons.addon', 'orders.items.menuItem', 'units.unitType'])
            ->whereNotIn('status', self::DEAD)
            ->whereDate('check_in', '<=', $date->toDateString())
            ->whereDate('check_out', '>', $date->toDateString())
            ->orderBy('check_in')
            ->get()
            ->map(function (Booking $booking): array {
                $addons = $booking->addonLabels();
                $food = $booking->foodLabels();
                $bill = $this->billing->grandTotal($booking);
                $owed = $this->billing->outstanding($booking);

                return [
                    'id' => $booking->id,
                    'kind' => 'booking',
                    'code' => $booking->code,
                    'name' => $booking->customer?->name ?? '-',
                    'tents' => implode(', ', $booking->tentLabels()),
                    'details' => [...$addons, ...$food],
                    'category' => match (true) {
                        $addons !== [] && $food !== [] => self::TENT_ADDON_FOOD,
                        $food !== [] => self::TENT_FOOD,
                        $addons !== [] => self::TENT_ADDON,
                        default => self::TENT,
                    },
                    'bill' => $bill,
                    'paid' => (int) $booking->paid_amount,
                    'owed' => $owed,
                    // "Lunas" covers the tent payment only; food billed later can still be owed.
                    'status' => $owed > 0 && $booking->status === BookingStatus::Paid ? 'Belum lunas' : $booking->status->getLabel(),
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function foodOnly(CarbonImmutable $date): array
    {
        return Order::with('items.menuItem')
            ->whereNull('booking_id')
            ->where('status', '!=', OrderStatus::Dibatalkan->value)
            ->whereDate('created_at', $date->toDateString())
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'kind' => 'order',
                'code' => $order->code,
                'name' => $order->customer_name ?? '-',
                'tents' => '-',
                'details' => $order->items->map(fn ($item) => $item->qty.'x '.$item->menuItem?->name)->all(),
                'category' => self::FOOD,
                'bill' => (int) $order->total,
                'paid' => $order->isPaid() ? (int) $order->total : 0,
                'owed' => $order->isPaid() ? 0 : (int) $order->total,
                'status' => $order->isPaid() ? 'Lunas' : 'Belum dibayar',
            ])
            ->all();
    }
}
