<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;

class ProgressChart extends ChartWidget
{
    protected ?string $heading = 'Okupansi per Tipe Unit';

    protected static ?int $sort = 7;

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
                ['label' => 'Terisi', 'data' => $series['occupied'], 'backgroundColor' => '#047857'],
                ['label' => 'Kosong', 'data' => $series['available'], 'backgroundColor' => '#d1d5db'],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
