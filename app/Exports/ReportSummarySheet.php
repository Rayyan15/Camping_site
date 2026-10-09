<?php

namespace App\Exports;

use App\Services\Reports\ReportTable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReportSummarySheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithTitle
{
    public function __construct(private readonly ReportTable $table) {}

    public function array(): array
    {
        return array_map(fn (array $line) => [$line['label'], $line['value']], $this->table->summary);
    }

    public function headings(): array
    {
        return ['Ringkasan', $this->table->period->label()];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function columnFormats(): array
    {
        return ['B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }
}
