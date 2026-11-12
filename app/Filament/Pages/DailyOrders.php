<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Services\DailyOrdersService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;

class DailyOrders extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    // Right under the dashboard: it is the first thing front office checks each day.
    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'Order Hari Ini';

    protected static ?string $title = 'Order Hari Ini';

    protected static ?string $slug = 'order-hari-ini';

    protected string $view = 'filament.pages.daily-orders';

    /** Selected day as Y-m-d. */
    public string $date = '';

    /** Category key from DailyOrdersService, or empty for all. */
    public string $category = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Booking::class) ?? false;
    }

    public function mount(): void
    {
        $this->date = $this->today()->toDateString();
    }

    public function shiftDay(int $days): void
    {
        $this->date = $this->selectedDate()->addDays($days)->toDateString();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = collect(app(DailyOrdersService::class)->forDate($this->selectedDate()));
        $counts = $rows->countBy('category');

        return [
            'rows' => $this->category === '' ? $rows : $rows->where('category', $this->category)->values(),
            'counts' => $counts,
            'total' => $rows->count(),
            'labels' => DailyOrdersService::LABELS,
            'owedTotal' => $rows->sum('owed'),
            'owedCount' => $rows->where('owed', '>', 0)->count(),
            'dayLabel' => $this->selectedDate()->locale('id')->translatedFormat('l, j F Y'),
            'isToday' => $this->selectedDate()->isSameDay($this->today()),
        ];
    }

    private function selectedDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->date, config('app.timezone'))->startOfDay();
        } catch (Throwable) {
            return $this->today();
        }
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }
}
