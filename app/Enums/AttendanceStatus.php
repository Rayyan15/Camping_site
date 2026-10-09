<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Backing values stay Indonesian because the attendances.status column was created with
 * these exact words (hadir, terlambat, izin, sakit, alpa); renaming would need a data migration for no gain.
 */
enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'hadir';
    case Late = 'terlambat';
    case Excused = 'izin';
    case Sick = 'sakit';
    case Absent = 'alpa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Excused => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Excused => 'info',
            self::Sick => 'gray',
            self::Absent => 'danger',
        };
    }

    /** Statuses the owner records by hand, each needing a written reason. */
    public function isManualAbsenceType(): bool
    {
        return in_array($this, [self::Excused, self::Sick, self::Absent], true);
    }

    /** Counts toward "kehadiran" in the recap. */
    public function countsAsAttended(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }
}
