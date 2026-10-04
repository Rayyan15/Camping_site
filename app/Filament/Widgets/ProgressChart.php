<?php
namespace App\Filament\Widgets;
use Filament\Widgets\ChartWidget;
class ProgressChart extends ChartWidget
{
    protected ?string $heading = 'Status Tenda';
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = 2;
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