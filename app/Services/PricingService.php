<?php

namespace App\Services;

use App\Exceptions\MenuItemUnavailableException;
use App\Models\Addon;
use App\Models\MenuItem;
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
        $day = $night->startOfDay();

        return $this->nightPrice($unitType, $day, $this->specialPricesBetween($unitType, $day, $day->addDay()));
    }

    /**
     * Total stay price of one unit of the given type. Special prices are loaded once for the whole stay.
     */
    public function stayTotal(UnitType $unitType, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): int
    {
        $total = 0;
        $night = CarbonImmutable::parse($checkIn)->startOfDay();
        $end = CarbonImmutable::parse($checkOut)->startOfDay();
        $specials = $this->specialPricesBetween($unitType, $night, $end);

        while ($night->lt($end)) {
            $total += $this->nightPrice($unitType, $night, $specials);
            $night = $night->addDay();
        }

        return $total;
    }

    /**
     * @param  array<string, int>  $specials  Y-m-d => price
     */
    private function nightPrice(UnitType $unitType, CarbonInterface $night, array $specials): int
    {
        $special = $specials[$night->toDateString()] ?? null;

        if ($special !== null) {
            return $special;
        }

        return $this->isWeekendNight($night)
            ? (int) $unitType->base_price_weekend
            : (int) $unitType->base_price_weekday;
    }

    /**
     * Nights in [$from, $to) keyed by date, one query regardless of stay length.
     *
     * @return array<string, int>
     */
    private function specialPricesBetween(UnitType $unitType, CarbonInterface $from, CarbonInterface $to): array
    {
        return SpecialPrice::where('unit_type_id', $unitType->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<', $to->toDateString())
            ->orderBy('id')
            ->get(['date', 'price'])
            ->mapWithKeys(fn (SpecialPrice $row) => [$row->date->toDateString() => (int) $row->price])
            ->all();
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
        return app(SettingRepository::class)->taxRate();
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
