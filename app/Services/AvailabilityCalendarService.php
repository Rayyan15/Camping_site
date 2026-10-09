<?php

namespace App\Services;

use App\Models\BookingUnit;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\UnitType;
use Carbon\CarbonImmutable;

/**
 * Month view of how many units of one type are free per night. A night is identified by the
 * date it starts, so a booking occupies [check_in, check_out) and a block covers start..end inclusive,
 * matching Unit::scopeFreeBetween. Three queries per month, whatever the number of units or bookings.
 */
class AvailabilityCalendarService
{
    /** At or below this many free units a night is flagged as running low. */
    public const LIMITED_STOCK_THRESHOLD = 2;

    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @return array{total_units: int, days: array<string, array{free: int, total: int, limited: bool}>}
     */
    public function forMonth(UnitType $unitType, CarbonImmutable $month): array
    {
        $first = $month->startOfMonth();
        $after = $first->addMonth();

        $unitIds = Unit::where('unit_type_id', $unitType->id)
            ->where('status', Unit::STATUS_ACTIVE)
            ->pluck('id')
            ->all();
        $total = count($unitIds);

        $busyUnitsByNight = $total === 0 ? [] : $this->busyUnitsByNight($unitIds, $first, $after);

        $days = [];
        for ($day = $first; $day->lt($after); $day = $day->addDay()) {
            $key = $day->toDateString();
            $free = $total - count($busyUnitsByNight[$key] ?? []);
            $days[$key] = ['free' => $free, 'total' => $total, 'limited' => $free > 0 && $free <= self::LIMITED_STOCK_THRESHOLD];
        }

        return ['total_units' => $total, 'days' => $days];
    }

    /**
     * Server-side price of one unit for the stay. The browser only displays this number.
     *
     * @return array{nights: int, total: int}
     */
    public function estimate(UnitType $unitType, string $checkIn, string $checkOut): array
    {
        return [
            'nights' => $this->pricing->nightsBetween($checkIn, $checkOut),
            'total' => $this->pricing->stayTotal($unitType, $checkIn, $checkOut),
        ];
    }

    /**
     * @param  array<int, int>  $unitIds
     * @return array<string, array<int, true>> night => set of unit ids that cannot be booked
     */
    private function busyUnitsByNight(array $unitIds, CarbonImmutable $first, CarbonImmutable $after): array
    {
        $busy = [];

        $lines = BookingUnit::whereIn('unit_id', $unitIds)
            ->whereDate('check_in', '<', $after->toDateString())
            ->whereDate('check_out', '>', $first->toDateString())
            ->whereHas('booking', fn ($booking) => $booking->occupying())
            ->get(['unit_id', 'check_in', 'check_out']);

        foreach ($lines as $line) {
            $this->markNights($busy, $line->unit_id, $line->check_in->toImmutable(), $line->check_out->toImmutable(), $first, $after);
        }

        $blocks = UnitBlock::whereIn('unit_id', $unitIds)
            ->whereDate('start_date', '<', $after->toDateString())
            ->whereDate('end_date', '>=', $first->toDateString())
            ->get(['unit_id', 'start_date', 'end_date']);

        foreach ($blocks as $block) {
            $this->markNights($busy, $block->unit_id, $block->start_date->toImmutable(), $block->end_date->toImmutable()->addDay(), $first, $after);
        }

        return $busy;
    }

    /**
     * @param  array<string, array<int, true>>  $busy
     */
    private function markNights(array &$busy, int $unitId, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $first, CarbonImmutable $after): void
    {
        $night = $from->startOfDay()->max($first);
        $limit = $until->startOfDay()->min($after);

        for (; $night->lt($limit); $night = $night->addDay()) {
            $busy[$night->toDateString()][$unitId] = true;
        }
    }
}
