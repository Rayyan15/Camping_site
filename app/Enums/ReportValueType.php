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

    /** Spreadsheet number format, so exported cells stay real numbers the owner can sum. */
    public function excelFormat(): string
    {
        return match ($this) {
            self::Text => '@',
            self::Count => '#,##0',
            self::Money => '"Rp" #,##0',
            self::Percent => '0"%"',
        };
    }

    public function isNumeric(): bool
    {
        return $this !== self::Text;
    }
}
