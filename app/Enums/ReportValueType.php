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

    /** Summary figures carry their unit in the value, so their labels stay short. */
    public function display(mixed $value): string
    {
        return match ($this) {
            self::Money => 'Rp '.$this->format($value),
            self::Percent => $this->format($value).'%',
            default => $this->format($value),
        };
    }

    /** Unit appended to a label where the value is a bare number (spreadsheet cells). */
    public function unitSuffix(): string
    {
        return match ($this) {
            self::Money => ' (Rp)',
            self::Percent => ' (%)',
            default => '',
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
