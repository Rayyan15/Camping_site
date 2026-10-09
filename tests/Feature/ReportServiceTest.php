<?php

namespace Tests\Feature;

use App\Enums\ReportPreset;
use App\Enums\ReportType;
use App\Services\Reports\InvalidReportPeriodException;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ReportFixtures;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;
    use ReportFixtures;

    private ReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 10:00:00');
        $this->reports = new ReportService;
        $this->seedOctober();
    }

    public function test_presets_resolve_to_whole_months_in_jakarta(): void
    {
        $this->assertSame('2026-10-01', ReportPeriod::fromPreset(ReportPreset::ThisMonth)->from->toDateString());
        $this->assertSame('2026-10-31', ReportPeriod::fromPreset(ReportPreset::ThisMonth)->to->toDateString());
        $this->assertSame('2026-09-01', ReportPeriod::fromPreset(ReportPreset::LastMonth)->from->toDateString());
        $this->assertSame('2026-09-30', ReportPeriod::fromPreset(ReportPreset::LastMonth)->to->toDateString());
        $this->assertSame(31, ReportPeriod::thisMonth()->days());
    }

    public function test_custom_period_is_validated(): void
    {
        $this->expectException(InvalidReportPeriodException::class);

        ReportPeriod::custom('2026-10-10', '2026-10-01');
    }

    public function test_custom_period_has_a_maximum_length(): void
    {
        $this->expectException(InvalidReportPeriodException::class);

        ReportPeriod::custom('2025-01-01', '2026-10-01');
    }

    public function test_booking_report_lists_bookings_by_check_in_with_status_counts(): void
    {
        $table = $this->reports->build(ReportType::Booking, ReportPeriod::thisMonth());

        $this->assertSame(['Kode', 'Pelanggan', 'Check-in', 'Check-out', 'Status', 'Total (Rp)', 'Dibayar (Rp)'], $table->headings());
        $this->assertSame([
            ['B-1', 'Andi Pratama', '01/10/2026', '04/10/2026', 'Lunas', 900000, 900000],
            ['B-6', 'Sari Utami', '05/10/2026', '07/10/2026', 'Lunas', 200000, 200000],
            ['B-3', 'Andi Pratama', '10/10/2026', '12/10/2026', 'Menunggu Bayar', 300000, 0],
            ['B-4', 'Sari Utami', '20/10/2026', '22/10/2026', 'Dibatalkan', 400000, 0],
            ['B-2', 'Sari Utami', '28/10/2026', '03/11/2026', 'Check-out', 1800000, 1800000],
        ], $table->rows);
        $this->assertSame(
            ['Jumlah booking' => 5, 'Menunggu Bayar' => 1, 'Lunas' => 2, 'Check-out' => 1, 'Dibatalkan' => 1],
            array_column($table->summary, 'value', 'label'),
        );
        $this->assertStringNotContainsString('081200000001', json_encode($table->rows));
    }

    public function test_booking_report_for_last_month_only_has_the_september_stay(): void
    {
        $table = $this->reports->build(ReportType::Booking, ReportPeriod::lastMonth());

        $this->assertSame([['B-5', 'Andi Pratama', '29/09/2026', '02/10/2026', 'Check-in', 450000, 450000]], $table->rows);
    }

    public function test_occupancy_counts_sold_nights_over_available_nights_per_type(): void
    {
        $table = $this->reports->build(ReportType::Occupancy, ReportPeriod::thisMonth());

        $this->assertSame([
            ['Dome', 2, 62, 7, 11],
            ['Tenda', 1, 28, 1, 4],
        ], $table->rows);
        $this->assertSame(
            ['Malam tersedia' => 90, 'Malam terisi' => 8, 'Okupansi (%)' => 9],
            array_column($table->summary, 'value', 'label'),
        );
        $this->assertStringContainsString('malam terisi dibagi malam tersedia', $table->definition);
    }

    public function test_top_menu_ranks_paid_order_items_inside_the_period(): void
    {
        $table = $this->reports->build(ReportType::TopMenu, ReportPeriod::thisMonth());

        $this->assertSame([
            ['Nasi Goreng', 4, 100000],
            ['Mie Rebus', 3, 60000],
            ['Kopi Hitam', 2, 30000],
        ], $table->rows);
        $this->assertSame(['Total terjual' => 9, 'Total omzet (Rp)' => 190000], array_column($table->summary, 'value', 'label'));
    }

    public function test_refund_report_groups_by_status_and_totals_only_transferred_refunds(): void
    {
        $table = $this->reports->build(ReportType::Refund, ReportPeriod::thisMonth());

        $this->assertSame([
            ['B-6', 'Sari Utami', '03/10/2026', 'Sudah Ditransfer', 50000, 'Ganti jadwal'],
            ['B-3', 'Andi Pratama', '12/10/2026', 'Ditolak', 30000, 'Di luar kebijakan'],
            ['B-4', 'Sari Utami', '21/10/2026', 'Diajukan', 100000, '-'],
        ], $table->rows);
        $this->assertSame(
            ['Diajukan' => 1, 'Ditolak' => 1, 'Sudah Ditransfer' => 1, 'Total dikembalikan (Rp)' => 50000],
            array_column($table->summary, 'value', 'label'),
        );
    }

    public function test_payments_by_method_separates_money_in_from_refunds_out(): void
    {
        $table = $this->reports->build(ReportType::PaymentMethod, ReportPeriod::thisMonth());

        $this->assertSame([
            ['Gateway', 'Masuk', 2, 985000],
            ['Transfer', 'Masuk', 1, 1800000],
            ['Tunai', 'Masuk', 2, 305000],
            ['Transfer', 'Refund keluar', 1, 50000],
        ], $table->rows);
        $this->assertSame(
            ['Total masuk (Rp)' => 3090000, 'Total refund keluar (Rp)' => 50000, 'Neto (Rp)' => 3040000],
            array_column($table->summary, 'value', 'label'),
        );
    }

    public function test_empty_period_yields_empty_rows_and_zero_totals(): void
    {
        $period = ReportPeriod::custom('2027-01-01', '2027-01-31');

        $this->assertSame([], $this->reports->build(ReportType::Booking, $period)->rows);
        $this->assertSame([], $this->reports->build(ReportType::TopMenu, $period)->rows);
        $this->assertSame(0, $this->reports->build(ReportType::Occupancy, $period)->summary[1]['value']);
        $this->assertSame(0, $this->reports->build(ReportType::PaymentMethod, $period)->summary[2]['value']);
    }

    public function test_file_name_follows_the_convention(): void
    {
        $table = $this->reports->build(ReportType::TopMenu, ReportPeriod::thisMonth());

        $this->assertSame('laporan-menu-terlaris-2026-10-01-2026-10-31.xlsx', $table->fileName('xlsx'));
    }
}
