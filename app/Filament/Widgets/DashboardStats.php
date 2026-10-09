<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_occupancy_dashboard');
    }

    protected ?string $heading = 'Ringkasan Hari Ini';

    protected function getColumns(): int
    {
        return auth()->user()?->can('view_financials') ? 4 : 3;
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class);

        $stats = [
            Stat::make('Check-in Hari Ini', $metrics->checkInsToday())
                ->description('Tamu yang tiba hari ini')
                ->descriptionIcon('heroicon-m-arrow-down-on-square'),
            Stat::make('Check-out Hari Ini', $metrics->checkOutsToday())
                ->description('Tamu yang pulang hari ini')
                ->descriptionIcon('heroicon-m-arrow-up-on-square'),
            Stat::make('Okupansi', $metrics->occupancyPercent().'%')
                ->description($metrics->occupiedUnitCount().' dari '.$metrics->activeUnitCount().' unit aktif terisi')
                ->descriptionIcon('heroicon-m-home-modern'),
            Stat::make('Pesanan F&B Aktif', $metrics->activeFoodOrderCount())
                ->description('Belum berstatus selesai')
                ->descriptionIcon('heroicon-m-fire'),
            Stat::make('Pre-order Menunggu Pembayaran', $metrics->preorderAwaitingPaymentCount())
                ->description('Belum masuk antrean dapur')
                ->descriptionIcon('heroicon-m-clock'),
        ];

        return auth()->user()?->can('view_financials')
            ? [...$stats, ...$this->revenueStats($metrics)]
            : $stats;
    }

    /** @return array<int, Stat> */
    private function revenueStats(DashboardMetricsService $metrics): array
    {
        $revenue = $metrics->monthlyRevenue();
        $month = 'Bulan '.$metrics->today()->translatedFormat('F Y');

        return [
            Stat::make('Pendapatan Camping', $this->rupiah($revenue['camping']))
                ->description($month)
                ->descriptionIcon('heroicon-m-banknotes'),
            Stat::make('Pendapatan F&B', $this->rupiah($revenue['food']))
                ->description($month)
                ->descriptionIcon('heroicon-m-banknotes'),
        ];
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
