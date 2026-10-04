<?php
namespace App\Filament\Widgets;
use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
class DashboardStats extends BaseWidget
{
    protected static ?int $sort = 2;
    protected function getColumns(): int { return 4; }
    protected function getStats(): array
    {
        return [
            Stat::make('Total Transaksi', 'Rp 14.5M')
                ->description('Meningkat dari bulan lalu')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->extraAttributes([
                    'class' => '!bg-emerald-700 !text-white rounded-2xl shadow-sm border-0',
                ]),
            Stat::make('Booking Selesai', '124')
                ->description('Meningkat dari bulan lalu')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->extraAttributes(['class' => 'rounded-2xl shadow-sm border border-gray-100']),
            Stat::make('Booking Berjalan', '12')
                ->description('Meningkat dari bulan lalu')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->extraAttributes(['class' => 'rounded-2xl shadow-sm border border-gray-100']),
            Stat::make('Booking Menunggu', '2')
                ->description('Sedang dibahas')
                ->extraAttributes(['class' => 'rounded-2xl shadow-sm border border-gray-100']),
        ];
    }
}