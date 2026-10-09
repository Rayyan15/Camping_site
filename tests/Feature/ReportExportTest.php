<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Models\User;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Support\ReportFixtures;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;
    use ReportFixtures;

    private const QUERY = ['periode' => 'bulan_ini'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 10:00:00');
        $this->seedOctober();
        $this->actingAs($this->makeUser(User::ROLE_OWNER));
    }

    public function test_every_report_downloads_as_xlsx_with_the_expected_name(): void
    {
        foreach (ReportType::cases() as $type) {
            $response = $this->get(route('admin.reports.excel', ['type' => $type->value] + self::QUERY));

            $response->assertOk();
            $this->assertStringContainsString(
                "laporan-{$type->value}-2026-10-01-2026-10-31.xlsx",
                $response->headers->get('Content-Disposition'),
            );
        }
    }

    public function test_booking_workbook_has_headings_numeric_rows_and_a_summary_sheet(): void
    {
        $sheets = $this->workbook(ReportType::Booking);

        $this->assertSame(['Kode', 'Pelanggan', 'Check-in', 'Check-out', 'Status', 'Total (Rp)', 'Dibayar (Rp)'], $sheets[0][0]);
        $this->assertSame(['B-1', 'Andi Pratama', '01/10/2026', '04/10/2026', 'Lunas', 900000, 900000], $sheets[0][1]);
        $this->assertCount(6, $sheets[0]);
        $this->assertSame(['Ringkasan', '01/10/2026 sampai 31/10/2026'], $sheets[1][0]);
        $this->assertSame(['Jumlah booking', 5], $sheets[1][1]);
    }

    public function test_payment_workbook_keeps_money_as_integers(): void
    {
        $sheets = $this->workbook(ReportType::PaymentMethod);

        $this->assertSame(['Metode', 'Arah', 'Transaksi', 'Total (Rp)'], $sheets[0][0]);
        $this->assertSame(['Gateway', 'Masuk', 2, 985000], $sheets[0][1]);
        $this->assertSame(['Neto (Rp)', 3040000], $sheets[1][3]);
    }

    public function test_every_report_downloads_as_pdf_with_the_expected_name(): void
    {
        foreach (ReportType::cases() as $type) {
            $response = $this->get(route('admin.reports.pdf', ['type' => $type->value] + self::QUERY));

            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString(
                "laporan-{$type->value}-2026-10-01-2026-10-31.pdf",
                $response->headers->get('Content-Disposition'),
            );
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_pdf_view_carries_title_period_rows_and_print_date_in_wib(): void
    {
        $html = view('reports.pdf.report', [
            'table' => app(ReportService::class)->build(ReportType::TopMenu, ReportPeriod::thisMonth()),
            'business' => config('site'),
            'printedAt' => '15/10/2026 10:00 WIB',
        ])->render();

        $this->assertStringContainsString('Laporan Menu terlaris', $html);
        $this->assertStringContainsString('Periode 01/10/2026 sampai 31/10/2026', $html);
        $this->assertStringContainsString('Nasi Goreng', $html);
        $this->assertStringContainsString('100.000', $html);
        $this->assertStringContainsString('Dicetak 15/10/2026 10:00 WIB', $html);
    }

    /** @return array<int, array<int, array<int, mixed>>> */
    private function workbook(ReportType $type): array
    {
        $response = $this->get(route('admin.reports.excel', ['type' => $type->value] + self::QUERY));
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);

        $path = $response->baseResponse->getFile()->getPathname();

        return Excel::toArray(new class implements ToArray, WithMultipleSheets
        {
            public function array(array $array): array
            {
                return $array;
            }

            public function sheets(): array
            {
                return [0 => $this, 1 => $this];
            }
        }, $path);
    }
}
