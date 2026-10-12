<?php

namespace Database\Seeders;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Values the client confirmed on 10 Oct 2026: no tax, full payment up front (down payment off),
 * check-in at 14:00 and check-out at 12:00. Existing rows are never overwritten, so owner edits
 * in Pengaturan survive a re-seed.
 */
class SettingsSeeder extends Seeder
{
    private const CONFIRMED = [
        [SettingKey::TaxRate, '0'],
        [SettingKey::DownPaymentPercent, '0'],
        [SettingKey::CheckInTime, SettingKey::DEFAULT_CHECK_IN_TIME],
        [SettingKey::CheckOutTime, SettingKey::DEFAULT_CHECK_OUT_TIME],
    ];

    public function run(): void
    {
        foreach (self::CONFIRMED as [$key, $value]) {
            Setting::firstOrCreate(['key' => $key->value], ['value' => $value]);
        }
    }
}
