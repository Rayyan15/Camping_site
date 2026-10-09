<?php

namespace App\Exports;

use App\Services\Reports\ReportTable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * One workbook per report: the data sheet, plus a summary sheet when the report has totals.
 */
class ReportExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private readonly ReportTable $table) {}

    public function sheets(): array
    {
        $sheets = [new ReportDataSheet($this->table)];

        if ($this->table->summary !== []) {
            $sheets[] = new ReportSummarySheet($this->table);
        }

        return $sheets;
    }
}
