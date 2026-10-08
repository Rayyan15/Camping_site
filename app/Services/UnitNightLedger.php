<?php

namespace App\Services;

use App\Exceptions\UnitUnavailableException;
use App\Models\BookingUnit;
use App\Models\BookingUnitNight;
use Carbon\CarbonPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * One row per unit and night. The unique (unit_id, night) key makes a double booking impossible
 * even if the row locking in BookingService is ever bypassed. Callers run inside a transaction.
 */
class UnitNightLedger
{
    /**
     * @param  Collection<int, BookingUnit>  $lines
     *
     * @throws UnitUnavailableException
     */
    public function claim(Collection $lines): void
    {
        foreach ($lines as $line) {
            $this->purgeStale($line);

            try {
                BookingUnitNight::insert($this->rowsFor($line));
            } catch (UniqueConstraintViolationException) {
                throw UnitUnavailableException::alreadyBooked();
            }
        }
    }

    /**
     * @param  array<int, int>  $bookingIds
     */
    public function release(array $bookingIds): void
    {
        BookingUnitNight::whereIn(
            'booking_unit_id',
            BookingUnit::whereIn('booking_id', $bookingIds)->select('id'),
        )->delete();
    }

    /**
     * Rows left behind by holds that lapsed without the expiry job running yet no longer block anybody.
     */
    private function purgeStale(BookingUnit $line): void
    {
        BookingUnitNight::where('unit_id', $line->unit_id)
            ->where('night', '>=', $line->check_in->toDateString())
            ->where('night', '<', $line->check_out->toDateString())
            ->whereDoesntHave('bookingUnit.booking', fn ($booking) => $booking->occupying())
            ->delete();
    }

    /**
     * @return array<int, array{booking_unit_id: int, unit_id: int, night: string}>
     */
    private function rowsFor(BookingUnit $line): array
    {
        $nights = CarbonPeriod::create($line->check_in->startOfDay(), $line->check_out->startOfDay()->subDay());

        $rows = [];

        foreach ($nights as $night) {
            $rows[] = ['booking_unit_id' => $line->id, 'unit_id' => $line->unit_id, 'night' => $night->toDateString()];
        }

        return $rows;
    }
}
