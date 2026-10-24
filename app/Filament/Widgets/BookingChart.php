<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\StylesCharts;
use App\Services\DashboardMetricsService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class BookingChart extends ChartWidget
{
    use StylesCharts;

    protected ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return __('Booking Masuk, 14 Hari Terakhir');
    }

    protected static ?int $sort = 5;

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
                    'label' => __('Booking'),
                    'data' => $series['data'],
                    'backgroundColor' => config('brand.forest.600'),
                    'borderRadius' => 6,
                    'borderSkipped' => 'start',
                    'maxBarThickness' => 28,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->styledChartOptions(legend: false);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
