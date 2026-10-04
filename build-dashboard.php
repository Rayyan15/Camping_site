<?php

// 1. AdminPanelProvider update (add columns, change color)
$provider = file_get_contents('app/Providers/Filament/AdminPanelProvider.php');
$provider = preg_replace("/'primary' => Color::Teal,/", "'primary' => Color::Emerald,", $provider);
file_put_contents('app/Providers/Filament/AdminPanelProvider.php', $provider);

// 2. DashboardHeader view
$headerView = <<<'HTML'
<x-filament-widgets::widget>
    <div class="flex items-center justify-between mb-4 mt-2">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Rencanakan, prioritaskan, dan kelola reservasi operasional dengan mudah.</p>
        </div>
        
        <div class="flex space-x-3 gap-3">
            <x-filament::button href="/admin/bookings/create" tag="a" color="primary" class="rounded-full px-6 shadow-sm">
                + Booking Baru
            </x-filament::button>
            <x-filament::button href="/admin/bookings" tag="a" color="gray" class="rounded-full px-6 shadow-sm bg-white text-gray-900 border border-gray-200">
                Data Lengkap
            </x-filament::button>
        </div>
    </div>
</x-filament-widgets::widget>
HTML;
file_put_contents('resources/views/filament/widgets/dashboard-header.blade.php', $headerView);

// 3. DashboardHeader class
$headerClass = <<<'PHP'
<?php
namespace App\Filament\Widgets;
use Filament\Widgets\Widget;
class DashboardHeader extends Widget
{
    protected string $view = 'filament.widgets.dashboard-header';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 1;
}
PHP;
file_put_contents('app/Filament/Widgets/DashboardHeader.php', $headerClass);

// 4. DashboardStats class
$statsClass = <<<'PHP'
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
PHP;
file_put_contents('app/Filament/Widgets/DashboardStats.php', $statsClass);

// 5. BookingChart (Bar)
$barClass = <<<'PHP'
<?php
namespace App\Filament\Widgets;
use Filament\Widgets\ChartWidget;
class BookingChart extends ChartWidget
{
    protected static ?string $heading = 'Analitik Reservasi';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 2;
    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Booking Masuk',
                    'data' => [12, 45, 74, 52, 22, 10, 30],
                    'backgroundColor' => ['#9ca3af', '#10b981', '#047857', '#064e3b', '#d1d5db', '#d1d5db', '#9ca3af'],
                    'borderRadius' => 20,
                ],
            ],
            'labels' => ['S', 'M', 'T', 'W', 'T', 'F', 'S'],
        ];
    }
    protected function getType(): string { return 'bar'; }
}
PHP;
file_put_contents('app/Filament/Widgets/BookingChart.php', $barClass);

// 6. Reminders Widget
$remindersView = <<<'HTML'
<x-filament-widgets::widget>
    <x-filament::section class="rounded-2xl shadow-sm border border-gray-100 h-full">
        <h3 class="font-bold text-lg mb-4 text-gray-800 dark:text-white">Pengingat</h3>
        <div class="mb-4">
            <h4 class="text-xl font-bold text-gray-900 dark:text-white">Check-in Grup SMA 1</h4>
            <p class="text-sm text-gray-500">Waktu : 14.00 PM - 16.00 PM</p>
        </div>
        <x-filament::button color="primary" class="w-full rounded-xl">
            <x-heroicon-o-video-camera class="w-5 h-5 mr-2 inline-block"/> Lihat CCTV
        </x-filament::button>
    </x-filament::section>
</x-filament-widgets::widget>
HTML;
file_put_contents('resources/views/filament/widgets/reminders.blade.php', $remindersView);

$remindersClass = <<<'PHP'
<?php
namespace App\Filament\Widgets;
use Filament\Widgets\Widget;
class RemindersWidget extends Widget
{
    protected string $view = 'filament.widgets.reminders';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;
}
PHP;
file_put_contents('app/Filament/Widgets/RemindersWidget.php', $remindersClass);

// 7. Team Collaboration Widget (Custom View)
$teamView = <<<'HTML'
<x-filament-widgets::widget>
    <x-filament::section class="rounded-2xl shadow-sm border border-gray-100 h-full">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-lg text-gray-800 dark:text-white">Aktivitas Karyawan</h3>
            <span class="text-xs border px-2 py-1 rounded-full">+ Tambah</span>
        </div>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-bold text-blue-600">A</div>
                    <div>
                        <p class="text-sm font-bold">Alexandra Deff</p>
                        <p class="text-xs text-gray-500">Merapikan Tenda Glamping A1</p>
                    </div>
                </div>
                <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Selesai</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center font-bold text-green-600">E</div>
                    <div>
                        <p class="text-sm font-bold">Edwin Adenike</p>
                        <p class="text-xs text-gray-500">Mengantar Makanan Tenda B2</p>
                    </div>
                </div>
                <span class="text-xs text-amber-600 bg-amber-50 px-2 py-1 rounded-full">Proses</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center font-bold text-purple-600">I</div>
                    <div>
                        <p class="text-sm font-bold">Isaac Oluwatemilorun</p>
                        <p class="text-xs text-gray-500">Perbaikan Resleting Tenda C</p>
                    </div>
                </div>
                <span class="text-xs text-rose-600 bg-rose-50 px-2 py-1 rounded-full">Tertunda</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
HTML;
file_put_contents('resources/views/filament/widgets/team.blade.php', $teamView);

$teamClass = <<<'PHP'
<?php
namespace App\Filament\Widgets;
use Filament\Widgets\Widget;
class TeamCollaborationWidget extends Widget
{
    protected string $view = 'filament.widgets.team';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = 2;
}
PHP;
file_put_contents('app/Filament/Widgets/TeamCollaborationWidget.php', $teamClass);

// 8. Progress Chart (Doughnut)
$doughnutClass = <<<'PHP'
<?php
namespace App\Filament\Widgets;
use Filament\Widgets\ChartWidget;
class ProgressChart extends ChartWidget
{
    protected static ?string $heading = 'Status Tenda';
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = 1;
    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'data' => [41, 35, 24],
                    'backgroundColor' => ['#064e3b', '#10b981', '#e5e7eb'],
                ],
            ],
            'labels' => ['Terisi', 'Dibersihkan', 'Kosong'],
        ];
    }
    protected function getType(): string { return 'doughnut'; }
    protected function getOptions(): array {
        return [
            'cutout' => '70%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
PHP;
file_put_contents('app/Filament/Widgets/ProgressChart.php', $doughnutClass);

// 9. Time Tracker Widget
$trackerView = <<<'HTML'
<x-filament-widgets::widget>
    <x-filament::section class="rounded-2xl shadow-sm border-0 h-full relative overflow-hidden" style="background: linear-gradient(135deg, #064e3b 0%, #047857 100%); color: white;">
        <div class="relative z-10 flex flex-col justify-between h-full">
            <h3 class="font-bold text-sm text-gray-200">Waktu Operasional Aktif</h3>
            <div class="text-center my-4">
                <h1 class="text-4xl font-bold font-mono tracking-wider">01:24:08</h1>
            </div>
            <div class="flex justify-center space-x-4">
                <div class="w-10 h-10 bg-white text-emerald-800 rounded-full flex items-center justify-center font-bold cursor-pointer hover:bg-gray-100">||</div>
                <div class="w-10 h-10 bg-red-500 text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-red-600">■</div>
            </div>
        </div>
        <!-- Decorative waves background -->
        <div class="absolute inset-0 opacity-20 pointer-events-none" style="background-image: repeating-radial-gradient(circle at 0 0, transparent 0, #10b981 10px), repeating-linear-gradient(#064e3b, #064e3b);"></div>
    </x-filament::section>
</x-filament-widgets::widget>
HTML;
file_put_contents('resources/views/filament/widgets/tracker.blade.php', $trackerView);

$trackerClass = <<<'PHP'
<?php
namespace App\Filament\Widgets;
use Filament\Widgets\Widget;
class TimeTrackerWidget extends Widget
{
    protected string $view = 'filament.widgets.tracker';
    protected static ?int $sort = 8;
    protected int | string | array $columnSpan = 1;
}
PHP;
file_put_contents('app/Filament/Widgets/TimeTrackerWidget.php', $trackerClass);

// 10. Update LatestBookings (Make it match Project list)
$latestClass = <<<'PHP'
<?php
namespace App\Filament\Widgets;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
class LatestBookings extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;
    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::query()->latest()->limit(5))
            ->heading('Daftar Tugas')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Tugas / Pelanggan')
                    ->description(fn (Booking $record): string => 'Jadwal: ' . $record->check_in->format('M d, Y'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger', default => 'gray',
                    }),
            ])
            ->paginated(false);
    }
}
PHP;
file_put_contents('app/Filament/Widgets/LatestBookings.php', $latestClass);

echo "Dashboard built successfully!\n";

