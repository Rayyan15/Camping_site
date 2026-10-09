<?php

namespace App\Filament\Pages;

use App\Enums\OccupancyCellStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Services\OccupancyCalendarService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class OkupansiKalender extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Kalender Okupansi';

    protected static ?string $title = 'Kalender Okupansi';

    protected static ?string $slug = 'okupansi-kalender';

    private const DEFAULT_DAYS = 14;

    private const DATE_FORMAT = 'Y-m-d';

    protected string $view = 'filament.pages.okupansi-kalender';

    public string $startDate = '';

    public int $days = self::DEFAULT_DAYS;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_occupancy_calendar') ?? false;
    }

    public function mount(): void
    {
        $this->startDate = $this->today()->toDateString();
    }

    public function previous(): void
    {
        $this->startDate = $this->start()->subDays($this->days)->toDateString();
    }

    public function next(): void
    {
        $this->startDate = $this->start()->addDays($this->days)->toDateString();
    }

    public function goToToday(): void
    {
        $this->startDate = $this->today()->toDateString();
    }

    public function setDays(int $days): void
    {
        if (in_array($days, OccupancyCalendarService::DAY_OPTIONS, true)) {
            $this->days = $days;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(OccupancyCalendarService::class);
        $grid = $service->grid($this->start(), $this->days);

        return [
            'grid' => $grid,
            'summary' => $service->tonight($this->today()),
            'statuses' => OccupancyCellStatus::cases(),
            'dayOptions' => OccupancyCalendarService::DAY_OPTIONS,
            'todayKey' => $this->today()->toDateString(),
            'unitCount' => collect($grid['groups'])->sum(fn (array $group) => count($group['units'])),
        ];
    }

    public function bookingAction(): Action
    {
        return Action::make('booking')
            ->modalHeading(fn (array $arguments): string => $this->detailHeading($arguments))
            ->modalContent(fn (array $arguments) => view('filament.pages.partials.okupansi-detail', $this->detail($arguments)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalWidth('lg');
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::instance(now())->startOfDay();
    }

    private function start(): CarbonImmutable
    {
        return $this->parseDate($this->startDate) ?? $this->today();
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) ? CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $value) : null;
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function detail(array $arguments): array
    {
        abort_unless(static::canAccess(), 403);

        $date = $this->parseDate($arguments['date'] ?? null);
        $detail = $date === null ? null : app(OccupancyCalendarService::class)->detail((int) ($arguments['unit'] ?? 0), $date);

        abort_if($detail === null, 404);

        $booking = $detail['booking'];

        return $detail + [
            'guest' => $detail['cell']['guest'],
            'editUrl' => $booking !== null && auth()->user()->can('update', $booking)
                ? BookingResource::getUrl('edit', ['record' => $booking])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function detailHeading(array $arguments): string
    {
        $detail = $this->detail($arguments);

        return 'Unit '.$detail['unit_code'].', '.$detail['date']->translatedFormat('j F Y');
    }
}
