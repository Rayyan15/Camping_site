<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Enums\ReportPreset;
use App\Enums\ReportType;
use App\Services\Reports\InvalidReportPeriodException;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use App\Services\Reports\ReportTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Laporan extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan & Keuangan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected static ?string $slug = 'laporan';

    protected string $view = 'filament.pages.laporan';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    public function mount(): void
    {
        $month = ReportPeriod::thisMonth();

        $this->form->fill([
            'type' => ReportType::Booking->value,
            'preset' => ReportPreset::ThisMonth->value,
            'from' => $month->from->toDateString(),
            'to' => $month->to->toDateString(),
        ]);
    }

    /**
     * The two dates are always on screen and always show the active range. Editing either one makes
     * the period custom; the month shortcuts fill them in again.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(['default' => 1, 'sm' => 2])
            ->components([
                DatePicker::make('from')
                    ->label('Dari')
                    ->native(false)
                    ->format('Y-m-d')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn () => $this->data['preset'] = ReportPreset::Custom->value),
                DatePicker::make('to')
                    ->label('Sampai')
                    ->native(false)
                    ->format('Y-m-d')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn () => $this->data['preset'] = ReportPreset::Custom->value),
            ]);
    }

    public function selectType(string $type): void
    {
        $this->data['type'] = (ReportType::tryFrom($type) ?? ReportType::Booking)->value;
    }

    public function selectPreset(string $preset): void
    {
        $this->data['preset'] = (ReportPreset::tryFrom($preset) ?? ReportPreset::ThisMonth)->value;

        if ($this->preset() === ReportPreset::Custom) {
            return;
        }

        // A month shortcut writes its dates into the inputs, so the inputs always show what the table covers.
        $period = ReportPeriod::fromPreset($this->preset());
        $this->data['from'] = $period->from->toDateString();
        $this->data['to'] = $period->to->toDateString();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $isCustom = $this->preset() === ReportPreset::Custom;
        $query = array_filter([
            'periode' => $this->preset()->value,
            'dari' => $isCustom ? ($this->data['from'] ?? null) : null,
            'sampai' => $isCustom ? ($this->data['to'] ?? null) : null,
        ]);
        $type = $this->type()->value;

        return [
            Action::make('excel')
                ->label('Unduh Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(route('admin.reports.excel', ['type' => $type] + $query)),
            Action::make('pdf')
                ->label('Unduh PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(route('admin.reports.pdf', ['type' => $type] + $query)),
        ];
    }

    /**
     * @return array{report: ReportTable|null, error: string|null, types: array<int, ReportType>, presets: array<string, string>, activeType: ReportType, activePreset: ReportPreset, isCustom: bool, statusTones: array<string, string>}
     */
    protected function getViewData(): array
    {
        $pills = [
            'types' => ReportType::cases(),
            'presets' => array_diff_key(ReportPreset::options(), [ReportPreset::Custom->value => true]),
            'activeType' => $this->type(),
            'activePreset' => $this->preset(),
            'isCustom' => $this->preset() === ReportPreset::Custom,
            'statusTones' => self::statusTones(),
        ];

        try {
            $period = ReportPeriod::fromPreset($this->preset(), $this->data['from'] ?? null, $this->data['to'] ?? null);
        } catch (InvalidReportPeriodException $exception) {
            return ['report' => null, 'error' => $exception->getMessage()] + $pills;
        }

        return ['report' => app(ReportService::class)->build($this->type(), $period), 'error' => null] + $pills;
    }

    /**
     * Status labels in report rows mapped to the same meaning the panel badges use, so "Lunas" reads
     * green and "Dibatalkan" reads ember here too.
     *
     * @return array<string, string> label => success|warning|danger|info|gray
     */
    private static function statusTones(): array
    {
        $tones = [];

        foreach ([...BookingStatus::cases(), ...RefundStatus::cases()] as $status) {
            $tones[$status->getLabel()] = $status->getColor();
        }

        return $tones;
    }

    private function type(): ReportType
    {
        return ReportType::tryFrom((string) ($this->data['type'] ?? '')) ?? ReportType::Booking;
    }

    private function preset(): ReportPreset
    {
        return ReportPreset::tryFrom((string) ($this->data['preset'] ?? '')) ?? ReportPreset::ThisMonth;
    }
}
