<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
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
            $items[] = ['icon' => 'heroicon-o-banknotes', 'value' => $rupiah($revenue['camping']), 'label' => 'Pendapatan camping bulan ini'];
            $items[] = ['icon' => 'heroicon-o-cake', 'value' => $rupiah($revenue['food']), 'label' => 'Pendapatan F&B bulan ini'];
        }

        return ['items' => $items];
    }
}
