<?php

namespace Database\Seeders;

use App\Enums\AddonUnit;
use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    /**
     * Keyed by name, so re-running (or UnitTypeSeeder having seeded the same rows) never duplicates.
     * Prices are placeholders until the owner confirms them. Extra Bed (an extra guest) stays at 0 and
     * inactive because the client has not given the per-person fee yet; the owner sets it in Add-on.
     */
    public function run(): void
    {
        $addons = [
            ['name' => 'Extra Bed', 'price' => 0, 'unit' => AddonUnit::PerNight, 'extra_guests' => 1, 'active' => false],
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
