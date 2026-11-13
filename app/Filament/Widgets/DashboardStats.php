<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use Filament\Widgets\Widget;

/**
 * Compact strip under the cards: the state of the site tonight and, for the owner only, this month's net revenue.
 */
class DashboardStats extends Widget
{
    protected string $view = 'filament.widgets.dashboard-stats';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_occupancy_dashboard');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $metrics = app(DashboardMetricsService::class);
        $rupiah = fn (int $amount): string => 'Rp '.number_format($amount, 0, ',', '.');

        $items = [
            ['icon' => 'heroicon-o-home-modern', 'value' => $metrics->occupiedUnitCount().' / '.$metrics->activeUnitCount(), 'label' => 'Unit terisi malam ini'],
            ['icon' => 'heroicon-o-chart-pie', 'value' => $metrics->occupancyPercent().'%', 'label' => 'Okupansi'],
            ['icon' => 'heroicon-o-users', 'value' => (string) $metrics->stayingGuestCount(), 'label' => 'Tamu menginap'],
            ['icon' => 'heroicon-o-arrow-left-start-on-rectangle', 'value' => (string) $metrics->checkOutsToday(), 'label' => 'Check-out hari ini'],
        ];

        if (auth()->user()?->can('view_financials')) {
            $revenue = $metrics->monthlyRevenue();
            $items[] = ['icon' => 'heroicon-o-banknotes', 'value' => $rupiah($revenue['camping']), 'label' => 'Uang masuk booking bulan ini (tenda, add-on, makanan booking)'];
            $items[] = ['icon' => 'heroicon-o-cake', 'value' => $rupiah($revenue['food']), 'label' => 'Uang masuk F&B QR dan walk-in bulan ini'];

            // Same numbers as the Menu terlaris report, so the owner can tell booking food from QR scans at a glance.
            $sold = array_column(app(ReportService::class)->topMenu(ReportPeriod::thisMonth())->summary, 'value', 'label');
            $items[] = [
                'icon' => 'heroicon-o-shopping-bag',
                'value' => $sold['Porsi terjual'].' porsi',
                'label' => "Terjual bulan ini: booking {$sold['Ikut booking']}, QR tenda {$sold['QR tenda']}, QR kafe {$sold['QR kafe']}, walk-in {$sold['Walk-in kasir']}",
            ];
        }

        return ['items' => $items];
    }
}
