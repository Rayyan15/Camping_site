<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Support\RawJs;

/**
 * One chart look for every dashboard chart, after shadcn/ui charts: no axis lines, no vertical grid,
 * faint dashed horizontal grid, muted 12px ticks, small round legend dots and a card-style tooltip.
 * Colors are read at draw time so light and dark mode each get their own ink and surface.
 * Returned as one RawJs object because Filament v4 does not serialize RawJs nested in an options array.
 */
trait StylesCharts
{
    protected function styledChartOptions(bool $rupiah = false, bool $stacked = false, bool $legend = true): RawJs
    {
        $format = $rupiah
            ? "(value) => 'Rp ' + Number(value).toLocaleString('id-ID')"
            : "(value) => Number(value).toLocaleString('id-ID')";
        $stackedJs = $stacked ? 'true' : 'false';
        $legendJs = $legend ? 'true' : 'false';

        return RawJs::make(<<<JS
            {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 8 } },
                scales: {
                    x: {
                        stacked: {$stackedJs},
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            color: () => document.documentElement.classList.contains('dark') ? '#94a199' : '#5f6e64',
                            font: { size: 12 },
                            maxRotation: 0,
                            autoSkipPadding: 12,
                        },
                    },
                    y: {
                        stacked: {$stackedJs},
                        beginAtZero: true,
                        border: { display: false, dash: [4, 4] },
                        grid: {
                            color: () => document.documentElement.classList.contains('dark') ? 'rgba(223, 233, 225, 0.08)' : 'rgba(31, 42, 34, 0.08)',
                            drawTicks: false,
                        },
                        ticks: {
                            color: () => document.documentElement.classList.contains('dark') ? '#94a199' : '#5f6e64',
                            font: { size: 12 },
                            padding: 8,
                            maxTicksLimit: 5,
                            precision: 0,
                            callback: {$format},
                        },
                    },
                },
                plugins: {
                    legend: {
                        display: {$legendJs},
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            boxHeight: 8,
                            padding: 16,
                            font: { size: 12 },
                            color: () => document.documentElement.classList.contains('dark') ? '#c3ccc6' : '#4f5c53',
                        },
                    },
                    tooltip: {
                        backgroundColor: () => document.documentElement.classList.contains('dark') ? '#1a251e' : '#ffffff',
                        borderColor: () => document.documentElement.classList.contains('dark') ? '#3a463e' : '#dde3df',
                        borderWidth: 1,
                        titleColor: () => document.documentElement.classList.contains('dark') ? '#ffffff' : '#1f2a22',
                        bodyColor: () => document.documentElement.classList.contains('dark') ? '#c3ccc6' : '#4a5a4f',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 10,
                        boxWidth: 8,
                        boxHeight: 8,
                        boxPadding: 6,
                        usePointStyle: true,
                        caretSize: 0,
                        callbacks: {
                            label: (item) => ' ' + item.dataset.label + ': ' + ({$format})(item.parsed.y),
                        },
                    },
                },
            }
        JS);
    }
}
