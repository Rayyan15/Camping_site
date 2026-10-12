<?php

namespace Database\Seeders;

use App\Enums\AddonUnit;
use App\Models\Addon;
use Illuminate\Database\Seeder;

/**
 * Reference data a fresh production database needs, with no demo content and no accounts.
 * Safe to run repeatedly: existing rows are never overwritten, so owner edits survive.
 *
 * Run with: php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    /** Inactive at price 0 so nothing is sold until the owner sets a real price and activates it. */
    private const ADDONS = [
        ['name' => 'Extra Bed', 'unit' => AddonUnit::PerNight, 'extra_guests' => 1],
        ['name' => 'Paket Kayu Bakar', 'unit' => AddonUnit::PerItem],
        ['name' => 'Sewa Matras Tambahan', 'unit' => AddonUnit::PerItem],
    ];

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RefundPolicySeeder::class,
            SettingsSeeder::class,
            EvaluationCriteriaSeeder::class,
        ]);

        $this->seedAddons();
    }

    private function seedAddons(): void
    {
        foreach (self::ADDONS as $addon) {
            Addon::firstOrCreate(
                ['name' => $addon['name']],
                ['price' => 0, 'unit' => $addon['unit'], 'extra_guests' => $addon['extra_guests'] ?? 0, 'is_active' => false],
            );
        }
    }
}
