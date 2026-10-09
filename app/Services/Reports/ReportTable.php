<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Enums\ReportValueType;

/**
 * A finished report: the one shape the screen, the spreadsheet and the PDF all render.
 */
final readonly class ReportTable
{
    /**
     * @param  list<array{label: string, type: ReportValueType}>  $columns
     * @param  list<list<int|string|null>>  $rows
     * @param  list<array{label: string, value: int|string, type: ReportValueType}>  $summary
     */
    public function __construct(
        public ReportType $type,
        public ReportPeriod $period,
        public array $columns,
        public array $rows,
        public array $summary = [],
        public string $definition = '',
        public bool $truncated = false,
    ) {}

    public function title(): string
    {
        return 'Laporan '.$this->type->label();
    }

    public function fileName(string $extension): string
    {
        return sprintf(
            'laporan-%s-%s-%s.%s',
            $this->type->value,
            $this->period->from->toDateString(),
            $this->period->to->toDateString(),
            $extension,
        );
    }

    /** @return list<string> */
    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }
}
