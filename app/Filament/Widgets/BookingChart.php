<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;

class BookingChart extends ChartWidget
{
    protected ?string $heading = 'Booking Masuk, 14 Hari Terakhir';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_occupancy_dashboard');
    }

    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $series = app(DashboardMetricsService::class)->bookingsPerDay(14);

        return [
            'datasets' => [
                [
                    'label' => 'Booking',
                    'data' => $series['data'],
                    'backgroundColor' => '#047857',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
