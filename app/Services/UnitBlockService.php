<?php

namespace App\Services;

use App\Exceptions\UnitBlockConflictException;
use App\Models\BookingUnit;
use App\Models\Unit;
use App\Models\UnitBlock;
use Illuminate\Support\Facades\DB;

/**
 * Blocks a unit for maintenance. The unit row is locked so a booking cannot be created
 * for the same dates between the conflict check and the insert.
 */
class UnitBlockService
{
    /**
     * @param  array{unit_id: int, start_date: string, end_date: string, reason?: string|null}  $data
     *
     * @throws UnitBlockConflictException
     */
    public function create(array $data): UnitBlock
    {
        return DB::transaction(function () use ($data) {
            $this->assertNoActiveBooking($data['unit_id'], $data['start_date'], $data['end_date']);

            return UnitBlock::create($data);
        });
    }

    /**
     * @param  array{unit_id: int, start_date: string, end_date: string, reason?: string|null}  $data
     *
     * @throws UnitBlockConflictException
     */
    public function update(UnitBlock $block, array $data): UnitBlock
    {
        return DB::transaction(function () use ($block, $data) {
            $this->assertNoActiveBooking($data['unit_id'], $data['start_date'], $data['end_date']);
            $block->update($data);

            return $block;
        });
    }

    private function assertNoActiveBooking(int $unitId, string $startDate, string $endDate): void
    {
        Unit::whereKey($unitId)->lockForUpdate()->firstOrFail();

        // A block covers start_date through end_date inclusive; a stay covers check_in up to, not including, check_out.
        $codes = BookingUnit::where('unit_id', $unitId)
            ->whereDate('check_in', '<=', $endDate)
            ->whereDate('check_out', '>', $startDate)
            ->whereHas('booking', fn ($booking) => $booking->occupying())
            ->with('booking:id,code')
            ->get()
            ->map(fn (BookingUnit $line) => $line->booking->code)
            ->unique()
            ->values()
            ->all();

        if ($codes !== []) {
            throw UnitBlockConflictException::overlapsBookings($codes);
        }
    }
}
