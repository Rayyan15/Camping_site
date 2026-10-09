<?php

namespace App\Enums;

enum ReportValueType: string
{
    case Text = 'text';
    case Count = 'count';
    case Money = 'money';
    case Percent = 'percent';

    /** Same grouping in the screen, the PDF and (as a number format) the spreadsheet. */
    public function format(mixed $value): string
    {
        return match ($this) {
            self::Text => (string) $value,
            default => number_format((int) $value, 0, ',', '.'),
        };
    }

    public function isNumeric(): bool
    {
        return $this !== self::Text;
    }
}
