<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttendanceSource: string implements HasLabel
{
    case Manual = 'manual';
    case Fingerprint = 'fingerprint';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Fingerprint => 'Fingerprint',
        };
    }
}
