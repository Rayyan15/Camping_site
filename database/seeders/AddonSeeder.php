<?php

namespace Database\Seeders;

use App\Enums\AddonUnit;
use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    /**
     * Keyed by name, so re-running (or UnitTypeSeeder having seeded the same rows) never duplicates.
     * Prices are placeholders until the owner confirms them. The extra bed stays at 0 and inactive
     * because the client has not given its fee yet; the owner sets it in Add-on.
     */
    public function run(): void
    {
        $addons = [
            ['name' => 'Extra bed + sarapan 2 pax', 'price' => 0, 'unit' => AddonUnit::PerNight, 'extra_guests' => 2, 'active' => false],
            ['name' => 'Paket Curug + Track ATV (2 orang)', 'price' => 250000, 'unit' => AddonUnit::PerItem],
            ['name' => 'Paket Kayu Bakar', 'price' => 50000, 'unit' => AddonUnit::PerItem],
            ['name' => 'Sewa Matras Tambahan', 'price' => 25000, 'unit' => AddonUnit::PerItem],
        ];

        foreach ($addons as $addon) {
            Addon::firstOrCreate(
                ['name' => $addon['name']],
                ['price' => $addon['price'], 'unit' => $addon['unit'], 'extra_guests' => $addon['extra_guests'] ?? 0, 'is_active' => $addon['active'] ?? true],
            );
        }
    }
}
