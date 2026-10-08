<?php

namespace App\Services;

use App\Exceptions\MenuItemUnavailableException;
use App\Models\Addon;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\SpecialPrice;
use App\Models\UnitType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Single source of truth for every price calculation. Amounts are integer rupiah.
 */
class PricingService
{
    public function nightsBetween(CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): int
    {
        return CarbonImmutable::parse($checkIn)->startOfDay()
            ->diffInDays(CarbonImmutable::parse($checkOut)->startOfDay());
    }

    /**
     * Price of the night that starts on $night: special price first, then weekday/weekend base.
     */
    public function priceForNight(UnitType $unitType, CarbonInterface $night): int
    {
        $special = SpecialPrice::where('unit_type_id', $unitType->id)
            ->whereDate('date', $night->toDateString())
            ->value('price');

        if ($special !== null) {
            return (int) $special;
        }

        return $this->isWeekendNight($night)
            ? (int) $unitType->base_price_weekend
            : (int) $unitType->base_price_weekday;
    }

    /**
     * Total stay price of one unit of the given type.
     */
    public function stayTotal(UnitType $unitType, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): int
    {
        $total = 0;
        $night = CarbonImmutable::parse($checkIn)->startOfDay();
        $end = CarbonImmutable::parse($checkOut)->startOfDay();

        while ($night->lt($end)) {
            $total += $this->priceForNight($unitType, $night);
            $night = $night->addDay();
        }

        return $total;
    }

    /**
     * Per-night add-ons (extra bed) are charged for every night of the stay.
     *
     * @param  array<int, int>  $addonQuantities  addon id => quantity
     * @return array<int, array{addon_id: int, qty: int, price: int, subtotal: int}>
     */
    public function addonLines(array $addonQuantities, int $nights): array
    {
        $addons = Addon::whereIn('id', array_keys($addonQuantities))
            ->where('is_active', true)
            ->get();

        return $addons->map(function (Addon $addon) use ($addonQuantities, $nights) {
            $qty = (int) $addonQuantities[$addon->id];
            $multiplier = $addon->isPerNight() ? max($nights, 1) : 1;

            return [
                'addon_id' => $addon->id,
                'qty' => $qty,
                'price' => (int) $addon->price,
                'subtotal' => (int) $addon->price * $qty * $multiplier,
            ];
        })->values()->all();
    }

    /**
     * Prices menu items from the database; prices sent by the browser are never used.
     *
     * @param  array<int, array{menu_item_id: int, qty: int, notes?: string|null}>  $items
     * @return array<int, array{menu_item_id: int, name: string, qty: int, price: int, subtotal: int, notes: string|null}>
     *
     * @throws MenuItemUnavailableException
     */
    public function menuLines(array $items): array
    {
        $menuItems = MenuItem::whereIn('id', array_column($items, 'menu_item_id'))->get()->keyBy('id');

        return array_map(function (array $item) use ($menuItems) {
            $menuItem = $menuItems->get($item['menu_item_id']) ?? throw MenuItemUnavailableException::notFound();

            if (! $menuItem->is_available) {
                throw MenuItemUnavailableException::notAvailable($menuItem->name);
            }

            return [
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'qty' => (int) $item['qty'],
                'price' => $menuItem->price,
                'subtotal' => $menuItem->price * (int) $item['qty'],
                'notes' => $item['notes'] ?? null,
            ];
        }, array_values($items));
    }

    public function taxRate(): float
    {
        $configured = Setting::where('key', 'tax_rate')->value('value');

        return $configured !== null ? (float) $configured : (float) config('booking.tax_rate');
    }

    public function taxFor(int $subtotal): int
    {
        return (int) round($subtotal * $this->taxRate());
    }

    private function isWeekendNight(CarbonInterface $night): bool
    {
        return in_array($night->dayOfWeekIso, config('booking.weekend_night_weekdays'), true);
    }
}
