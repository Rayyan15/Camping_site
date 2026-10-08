<?php

namespace Database\Seeders;

use App\Models\DiningSpot;
use App\Models\Unit;
use App\Services\DiningSpotService;
use Illuminate\Database\Seeder;

class DiningSpotSeeder extends Seeder
{
    private const GENERIC_TABLES = 6;

    /**
     * One QR per tent unit plus numbered tables. Existing spots keep their token so printed QRs stay valid.
     */
    public function run(DiningSpotService $spots): void
    {
        Unit::orderBy('code')->each(function (Unit $unit) use ($spots) {
            if (! DiningSpot::where('unit_id', $unit->id)->exists()) {
                $spots->create('Tenda '.$unit->code, DiningSpot::TYPE_TENT, $unit->id);
            }
        });

        foreach (range(1, self::GENERIC_TABLES) as $number) {
            $name = 'Meja '.$number;

            if (! DiningSpot::where('name', $name)->exists()) {
                $spots->create($name, DiningSpot::TYPE_TABLE);
            }
        }
    }
}
