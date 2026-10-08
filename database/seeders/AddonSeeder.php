<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    /**
     * Keyed by name, so re-running (or UnitTypeSeeder having seeded the same rows) never duplicates.
     * Prices are placeholders until the owner confirms them.
     */
    public function run(): void
    {
        $addons = [
            ['name' => 'Extra Bed', 'price' => 75000, 'unit' => Addon::UNIT_PER_NIGHT],
            ['name' => 'Paket Kayu Bakar', 'price' => 50000, 'unit' => Addon::UNIT_PER_ITEM],
            ['name' => 'Sewa Matras Tambahan', 'price' => 25000, 'unit' => Addon::UNIT_PER_ITEM],
        ];

        foreach ($addons as $addon) {
            Addon::firstOrCreate(
                ['name' => $addon['name']],
                ['price' => $addon['price'], 'unit' => $addon['unit'], 'is_active' => true],
            );
        }
    }
}
