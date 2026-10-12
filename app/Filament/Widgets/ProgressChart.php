<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\StylesCharts;
use App\Services\DashboardMetricsService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ProgressChart extends ChartWidget
{
    use StylesCharts;

    protected ?string $maxHeight = '280px';

    protected ?string $heading = 'Okupansi per Tipe Unit';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_occupancy_dashboard');
    }

    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $series = app(DashboardMetricsService::class)->occupancyByUnitType();

        return [
            'datasets' => [
                ['label' => 'Terisi', 'data' => $series['occupied'], 'backgroundColor' => config('brand.forest.600'), 'borderRadius' => 6, 'borderSkipped' => false, 'maxBarThickness' => 32],
                [
                    'label' => 'Kosong',
                    'data' => $series['available'],
                    'backgroundColor' => 'rgba(127, 163, 138, 0.35)',
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'maxBarThickness' => 32,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->styledChartOptions(stacked: true);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
