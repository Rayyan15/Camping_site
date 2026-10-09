<?php

namespace App\Enums;

enum ReportPreset: string
{
    case ThisMonth = 'bulan_ini';
    case LastMonth = 'bulan_lalu';
    case Custom = 'kustom';

    public function label(): string
    {
        return match ($this) {
            self::ThisMonth => 'Bulan ini',
            self::LastMonth => 'Bulan lalu',
            self::Custom => 'Kustom',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $preset) => [$preset->value => $preset->label()])->all();
    }
}
