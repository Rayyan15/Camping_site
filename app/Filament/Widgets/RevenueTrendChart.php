<?php

namespace App\Filament\Widgets;

use App\Enums\RevenueGranularity;
use App\Filament\Widgets\Concerns\StylesCharts;
use App\Services\Reports\InvalidReportPeriodException;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\RevenueTrendService;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Net revenue over time. A line chart fits two series that both read against one rupiah axis;
 * stacked bars would break when a refund pushes camping below zero.
 * Camping is a solid forest line, food a dashed sand-dark line with square points, so the two
 * stay apart without relying on hue alone (forest-600 6.59 on white, sand-dark 5.28 on white and 3.31 on dark).
 */
class RevenueTrendChart extends ChartWidget
{
    use HasFiltersSchema;
    use StylesCharts;

    protected static ?int $sort = 6;

    public function getHeading(): ?string
    {
        return __('Pendapatan Bersih');
    }

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_financials');
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('granularity')
                ->label(__('Agregasi'))
                ->options(RevenueGranularity::options())
                ->default(RevenueGranularity::Day->value)
                ->native(false)
                ->selectablePlaceholder(false),
            DatePicker::make('from')
                ->label('Dari')
                ->default(fn () => ReportPeriod::thisMonth()->from->toDateString())
                ->native(false),
            DatePicker::make('to')
                ->label('Sampai')
                ->default(fn () => ReportPeriod::thisMonth()->to->toDateString())
                ->native(false),
        ]);
    }

    public function getDescription(): string|Htmlable|null
    {
        $period = $this->period();

        if ($period === null) {
            return 'Rentang tanggal tidak valid, maksimal '.ReportPeriod::MAX_DAYS.' hari. Menampilkan bulan ini.';
        }

        return 'Pembayaran masuk dikurangi refund keluar, '.$period->label().'. Refund mengurangi garis camping.';
    }

    protected function getData(): array
    {
        $series = app(RevenueTrendService::class)->series($this->period() ?? ReportPeriod::thisMonth(), $this->granularity());

        return [
            'datasets' => [
                [
                    'label' => 'Camping',
                    'data' => $series['camping'],
                    'borderColor' => config('brand.forest.600'),
                    'backgroundColor' => 'rgba(60, 102, 73, 0.12)',
                    'fill' => 'origin',
                    'pointStyle' => 'circle',
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 5,
                    'tension' => 0.35,
                    'cubicInterpolationMode' => 'monotone',
                ],
                [
                    'label' => 'F&B',
                    'data' => $series['food'],
                    'borderColor' => config('brand.sand_dark'),
                    'backgroundColor' => config('brand.sand_dark'),
                    'pointStyle' => 'rect',
                    'borderWidth' => 2,
                    'borderDash' => [6, 4],
                    'pointRadius' => 0,
                    'pointHoverRadius' => 5,
                    'tension' => 0.35,
                    'cubicInterpolationMode' => 'monotone',
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->styledChartOptions(rupiah: true);
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function granularity(): RevenueGranularity
    {
        return RevenueGranularity::tryFrom((string) ($this->filters['granularity'] ?? '')) ?? RevenueGranularity::Day;
    }

    private function period(): ?ReportPeriod
    {
        try {
            return ReportPeriod::custom(
                $this->filters['from'] ?? CarbonImmutable::now()->startOfMonth()->toDateString(),
                $this->filters['to'] ?? CarbonImmutable::now()->endOfMonth()->toDateString(),
            );
        } catch (InvalidReportPeriodException) {
            return null;
        }
    }
}
