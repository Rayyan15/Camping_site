<?php

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Central read and write access to the settings table. Reads share one cached map that
 * is dropped whenever a Setting row is saved or deleted, so no caller sees a stale value.
 */
class SettingRepository
{
    public const CACHE_KEY = 'settings.all';

    public function get(SettingKey $key): ?string
    {
        return $this->all()[$key->value] ?? null;
    }

    /** Tax as a fraction, 0.11 means 11 percent. */
    public function taxRate(): float
    {
        $configured = $this->get(SettingKey::TaxRate);

        return $configured !== null ? (float) $configured : (float) config('booking.tax_rate');
    }

    public function checkInTime(): string
    {
        return $this->get(SettingKey::CheckInTime) ?? SettingKey::DEFAULT_CHECK_IN_TIME;
    }

    public function checkOutTime(): string
    {
        return $this->get(SettingKey::CheckOutTime) ?? SettingKey::DEFAULT_CHECK_OUT_TIME;
    }

    /** Zero (or 100) turns the down payment option off at checkout. */
    public function downPaymentPercent(): int
    {
        $configured = $this->get(SettingKey::DownPaymentPercent);

        return $configured !== null ? (int) $configured : SettingKey::DEFAULT_DOWN_PAYMENT_PERCENT;
    }

    /**
     * @return array{tax_rate_percent: float, check_in_time: string, check_out_time: string, down_payment_percent: int}
     */
    public function formValues(): array
    {
        return [
            'tax_rate_percent' => round($this->taxRate() * 100, 2),
            'check_in_time' => $this->checkInTime(),
            'check_out_time' => $this->checkOutTime(),
            'down_payment_percent' => $this->downPaymentPercent(),
        ];
    }

    /**
     * @param  array{tax_rate_percent: int|float|string, check_in_time: string, check_out_time: string, down_payment_percent: int|string}  $values
     */
    public function saveFormValues(array $values): void
    {
        DB::transaction(function () use ($values) {
            $this->put(SettingKey::TaxRate, (string) round((float) $values['tax_rate_percent'] / 100, 4));
            $this->put(SettingKey::CheckInTime, $values['check_in_time']);
            $this->put(SettingKey::CheckOutTime, $values['check_out_time']);
            $this->put(SettingKey::DownPaymentPercent, (string) (int) $values['down_payment_percent']);

            // The owner has now reviewed every placeholder the production seeder listed.
            Setting::where('key', SettingKey::PendingOwnerConfirmation->value)->get()->each->delete();
        });
    }

    public function put(SettingKey $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key->value], ['value' => $value]);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string|null> */
    private function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::pluck('value', 'key')->all(),
        );
    }
}
