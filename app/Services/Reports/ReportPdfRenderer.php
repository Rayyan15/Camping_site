<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\PDF;
use Carbon\CarbonImmutable;

class ReportPdfRenderer
{
    public function render(ReportTable $table): PDF
    {
        return app('dompdf.wrapper')
            ->setPaper('a4', count($table->columns) > 5 ? 'landscape' : 'portrait')
            ->loadView('reports.pdf.report', [
                'table' => $table,
                'business' => config('site'),
                'printedAt' => CarbonImmutable::now()->format('d/m/Y H:i').' WIB',
            ]);
    }
}
