<?php
namespace App\Filament\Widgets;
use Filament\Widgets\ChartWidget;
class BookingChart extends ChartWidget
{
    protected ?string $heading = 'Analitik Reservasi';
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