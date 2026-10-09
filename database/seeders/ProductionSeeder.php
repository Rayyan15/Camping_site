<?php

namespace Database\Seeders;

use App\Enums\AddonUnit;
use App\Models\Addon;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Reference data a fresh production database needs, with no demo content and no accounts.
 * Safe to run repeatedly: existing rows are never overwritten, so owner edits survive.
 *
 * Run with: php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    /** Settings key that lists the placeholder keys the owner still has to confirm. */
    public const PENDING_CONFIRMATION_KEY = 'pending_owner_confirmation';

    /**
     * Placeholder values, to be confirmed by the owner in Pengaturan before opening bookings.
     * Tax rate overrides config booking.tax_rate; check-in and check-out times are informational.
     */
    private const PLACEHOLDER_SETTINGS = [
        'tax_rate' => '0.11',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ];

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
        ]);

        $this->seedSettings();
        $this->seedAddons();
    }

    private function seedSettings(): void
    {
        foreach (self::PLACEHOLDER_SETTINGS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::firstOrCreate(
            ['key' => self::PENDING_CONFIRMATION_KEY],
            ['value' => implode(',', array_keys(self::PLACEHOLDER_SETTINGS))],
        );
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
