<?php

namespace App\Exports;

use App\Services\Reports\ReportTable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReportDataSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithTitle
{
    public function __construct(private readonly ReportTable $table) {}

    public function array(): array
    {
        return $this->table->rows;
    }

    public function headings(): array
    {
        return $this->table->headings();
    }

    public function title(): string
    {
        return 'Data';
    }

    /** Money and counts stay real numbers so the owner can sum them; only the display grouping is set. */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->table->columns as $index => $column) {
            if ($column['type']->isNumeric()) {
                $formats[Coordinate::stringFromColumnIndex($index + 1)] = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
            }
        }

        return $formats;
    }
}
