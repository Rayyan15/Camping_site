<?php

namespace App\Exports;

use App\Services\Reports\ReportTable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSummarySheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use StylesReportSheet;

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
        return ['B' => '#,##0'];
    }
}
