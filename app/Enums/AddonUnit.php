<?php

namespace App\Enums;

enum AddonUnit: string
{
    case PerNight = 'per malam';
    case PerItem = 'per item';

    public function label(): string
    {
        return match ($this) {
            self::PerNight => 'Per malam',
            self::PerItem => 'Per item',
        };
    }

    /**
     * @return array<string, string> stored value => Indonesian label
     */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $unit) => ['value' => $unit->value, 'label' => $unit->label()], self::cases()),
            'label',
            'value',
        );
    }
}
