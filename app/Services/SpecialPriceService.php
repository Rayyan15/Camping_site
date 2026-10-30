<?php

namespace App\Services;

use App\Models\SpecialPrice;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SpecialPriceService
{
    // A year covers a whole season or holiday period without letting a typo create years of rows.
    public const MAX_RANGE_NIGHTS = 366;

    /**
     * Pricing reads one row per night, so a range is stored as one row per night. Nights that
     * already have a special price take the new one, which is what an owner editing a season expects.
     */
    public function saveRange(int $unitTypeId, string $from, ?string $until, int $price, ?string $note): SpecialPrice
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($until ?: $from)->startOfDay();

        return DB::transaction(function () use ($unitTypeId, $start, $end, $price, $note) {
            $first = null;

            foreach (CarbonPeriod::create($start, $end) as $night) {
                $row = SpecialPrice::where('unit_type_id', $unitTypeId)->whereDate('date', $night)->first()
                    ?? new SpecialPrice(['unit_type_id' => $unitTypeId, 'date' => $night->toDateString()]);
                $row->fill(['price' => $price, 'note' => $note])->save();
                $first ??= $row;
            }

            return $first;
        });
    }
}
