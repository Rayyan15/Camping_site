<?php

namespace App\Filament\Pages;

use App\Enums\ReportPreset;
use App\Enums\ReportType;
use App\Services\Reports\InvalidReportPeriodException;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use App\Services\Reports\ReportTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
        $this->form->fill([
            'type' => ReportType::Booking->value,
            'preset' => ReportPreset::ThisMonth->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(['default' => 1, 'md' => 4])
            ->components([
                Select::make('type')
                    ->label('Jenis laporan')
                    ->options(ReportType::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->live(),
                Select::make('preset')
                    ->label('Periode')
                    ->options(ReportPreset::options())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->live(),
                DatePicker::make('from')
                    ->label('Dari')
                    ->native(false)
                    ->visible(fn (): bool => $this->preset() === ReportPreset::Custom)
                    ->live(),
                DatePicker::make('to')
                    ->label('Sampai')
                    ->native(false)
                    ->visible(fn (): bool => $this->preset() === ReportPreset::Custom)
                    ->live(),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $query = array_filter([
            'periode' => $this->preset()->value,
            'dari' => $this->data['from'] ?? null,
            'sampai' => $this->data['to'] ?? null,
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
     * @return array{report: ReportTable|null, error: string|null}
     */
    protected function getViewData(): array
    {
        try {
            $period = ReportPeriod::fromPreset($this->preset(), $this->data['from'] ?? null, $this->data['to'] ?? null);
        } catch (InvalidReportPeriodException $exception) {
            return ['report' => null, 'error' => $exception->getMessage()];
        }

        return ['report' => app(ReportService::class)->build($this->type(), $period), 'error' => null];
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
