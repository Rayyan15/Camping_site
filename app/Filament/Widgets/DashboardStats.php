<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class DashboardStats extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $today = Carbon::today();

        $omzetHariIni = Payment::whereDate('created_at', $today)
            ->where('status', 'paid')
            ->sum('amount');

        $bookingHariIni = Booking::whereDate('created_at', $today)->count();
        $bookingMenunggu = Booking::where('status', 'pending')->count();
        
        $tendaTerisi = Booking::where('status', 'confirmed')
            ->where('check_in', '<=', $today)
            ->where('check_out', '>', $today)
            ->withCount('units')
            ->get()
            ->sum('units_count');

        return [
            Stat::make('Omzet Hari Ini', 'Rp ' . number_format($omzetHariIni, 0, ',', '.'))
                ->icon('heroicon-o-currency-dollar')
                ->description('Total pembayaran masuk')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
                
            Stat::make('Booking Hari Ini', $bookingHariIni)
                ->icon('heroicon-o-calendar')
                ->description('Pemesanan baru hari ini'),
                
            Stat::make('Booking Menunggu', $bookingMenunggu)
                ->icon('heroicon-o-clock')
                ->description('Menunggu pembayaran')
                ->color('warning'),
                
            Stat::make('Tenda Terisi', $tendaTerisi)
                ->icon('heroicon-o-home')
                ->description('Tenda aktif digunakan hari ini'),
        ];
    }
}
