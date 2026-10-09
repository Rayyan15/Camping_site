<?php

namespace Tests\Feature;

use App\Enums\RevenueGranularity;
use App\Filament\Widgets\RevenueTrendChart;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\RevenueTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ReportFixtures;
use Tests\TestCase;

class RevenueChartTest extends TestCase
{
    use RefreshDatabase;
    use ReportFixtures;

    private RevenueTrendService $trend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 10:00:00');
        $this->trend = new RevenueTrendService;
        $this->seedOctober();
    }

    public function test_daily_series_splits_camping_and_food_and_refund_lowers_camping(): void
    {
        $series = $this->trend->series(ReportPeriod::thisMonth(), RevenueGranularity::Day);

        $this->assertCount(31, $series['labels']);
        $this->assertSame('01/10', $series['labels'][0]);
        $this->assertSame([900000, 1800000, 200000, -50000], array_slice($series['camping'], 0, 4));
        $this->assertSame(105000, $series['food'][1]);
        $this->assertSame(85000, $series['food'][5]);
        $this->assertSame(0, $series['camping'][10]);
    }

    public function test_weekly_series_buckets_by_monday_and_clamps_to_the_period_start(): void
    {
        $series = $this->trend->series(ReportPeriod::thisMonth(), RevenueGranularity::Week);

        $this->assertSame(['Mgg 01/10', 'Mgg 05/10', 'Mgg 12/10', 'Mgg 19/10', 'Mgg 26/10'], $series['labels']);
        $this->assertSame([2850000, 0, 0, 0, 0], $series['camping']);
        $this->assertSame([105000, 85000, 0, 0, 0], $series['food']);
    }

    public function test_monthly_series_matches_the_dashboard_monthly_revenue(): void
    {
        $series = $this->trend->series(ReportPeriod::thisMonth(), RevenueGranularity::Month);
        $dashboard = (new DashboardMetricsService)->monthlyRevenue();

        $this->assertSame(['10/2026'], $series['labels']);
        $this->assertSame([2850000], $series['camping']);
        $this->assertSame([190000], $series['food']);
        $this->assertSame($dashboard['camping'], $series['camping'][0]);
        $this->assertSame($dashboard['food'], $series['food'][0]);
    }

    public function test_range_spanning_two_months_groups_by_calendar_month(): void
    {
        $series = $this->trend->series(ReportPeriod::custom('2026-09-30', '2026-10-31'), RevenueGranularity::Month);

        $this->assertSame(['09/2026', '10/2026'], $series['labels']);
        $this->assertSame([0, 2850000], $series['camping']);
    }

    public function test_pending_payments_are_ignored(): void
    {
        $series = $this->trend->series(ReportPeriod::custom('2026-10-05', '2026-10-05'), RevenueGranularity::Day);

        $this->assertSame([0], $series['camping']);
    }

    public function test_only_owner_can_view_the_widget(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_OWNER));
        $this->assertTrue(RevenueTrendChart::canView());
        Livewire::test(RevenueTrendChart::class)->assertSee('Pendapatan Bersih');

        $this->actingAs($this->makeUser(User::ROLE_FRONT_OFFICE));
        $this->assertFalse(RevenueTrendChart::canView());

        $this->actingAs($this->makeUser(User::ROLE_CASHIER));
        $this->assertFalse(RevenueTrendChart::canView());
    }
}
