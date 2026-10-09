<?php

namespace App\Filament\Widgets;

use App\Enums\RevenueGranularity;
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
 * Camping is a solid emerald line, food a dashed amber line with square points, so the two
 * stay apart without relying on hue alone. Both pass 3:1 on the light and dark surface.
 */
class RevenueTrendChart extends ChartWidget
{
    use HasFiltersSchema;

    private const CAMPING_COLOR = '#047857';

    private const FOOD_COLOR = '#B45309';

    protected static ?int $sort = 4;

    protected ?string $heading = 'Pendapatan Bersih';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_financials');
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('granularity')
                ->label('Agregasi')
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
                    'borderColor' => self::CAMPING_COLOR,
                    'backgroundColor' => self::CAMPING_COLOR,
                    'pointStyle' => 'circle',
                    'borderWidth' => 2,
                    'pointRadius' => 4,
                    'tension' => 0,
                ],
                [
                    'label' => 'F&B',
                    'data' => $series['food'],
                    'borderColor' => self::FOOD_COLOR,
                    'backgroundColor' => self::FOOD_COLOR,
                    'pointStyle' => 'rect',
                    'borderWidth' => 2,
                    'borderDash' => [6, 4],
                    'pointRadius' => 4,
                    'tension' => 0,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'y' => [
                    'ticks' => ['callback' => RawJs::make('(value) => "Rp " + Number(value).toLocaleString("id-ID")')],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => true, 'labels' => ['usePointStyle' => true]],
                'tooltip' => [
                    'callbacks' => [
                        'label' => RawJs::make('(item) => item.dataset.label + ": Rp " + Number(item.parsed.y).toLocaleString("id-ID")'),
                    ],
                ],
            ],
        ];
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
