<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportType;
use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportReportRequest;
use App\Services\Reports\InvalidReportPeriodException;
use App\Services\Reports\ReportPdfRenderer;
use App\Services\Reports\ReportService;
use App\Services\Reports\ReportTable;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function excel(ExportReportRequest $request, ReportType $type): BinaryFileResponse
    {
        $table = $this->table($request, $type);

        return Excel::download(new ReportExport($table), $table->fileName('xlsx'));
    }

    public function pdf(ExportReportRequest $request, ReportType $type, ReportPdfRenderer $renderer): Response
    {
        $table = $this->table($request, $type);

        return $renderer->render($table)->download($table->fileName('pdf'));
    }

    private function table(ExportReportRequest $request, ReportType $type): ReportTable
    {
        try {
            return $this->reports->build($type, $request->period());
        } catch (InvalidReportPeriodException $exception) {
            abort(422, $exception->getMessage());
        }
    }
}
