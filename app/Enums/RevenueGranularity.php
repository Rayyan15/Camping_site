<?php

namespace App\Enums;

enum RevenueGranularity: string
{
    case Day = 'hari';
    case Week = 'minggu';
    case Month = 'bulan';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Per hari',
            self::Week => 'Per minggu',
            self::Month => 'Per bulan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
