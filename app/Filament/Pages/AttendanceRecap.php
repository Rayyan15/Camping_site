<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceRecapService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class AttendanceRecap extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'SDM & Karyawan';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Rekap Kehadiran';

    protected static ?string $title = 'Rekap Kehadiran';

    protected static ?string $slug = 'rekap-kehadiran';

    protected string $view = 'filament.pages.attendance-recap';

    private const MONTH_OPTIONS = 12;

    /** Selected period as Y-m. */
    public string $month = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Attendance::class) ?? false;
    }

    public function mount(): void
    {
        $this->month = CarbonImmutable::now(config('app.timezone'))->format('Y-m');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monthOptions = $this->monthOptions();
        $selected = array_key_exists($this->month, $monthOptions) ? $this->month : array_key_first($monthOptions);
        $month = CarbonImmutable::createFromFormat('Y-m-d', $selected.'-01', config('app.timezone'));

        return [
            'rows' => app(AttendanceRecapService::class)->forMonth($month, $this->visibleEmployees()),
            'monthOptions' => $monthOptions,
            'seesEveryone' => auth()->user()->can('view_any_attendance'),
        ];
    }

    /** @return Collection<int, Employee> */
    private function visibleEmployees(): Collection
    {
        $query = Employee::query()->orderBy('name');

        if (! auth()->user()->can('view_any_attendance')) {
            $query->where('user_id', auth()->id());
        }

        return $query->get();
    }

    /** @return array<string, string> */
    private function monthOptions(): array
    {
        $current = CarbonImmutable::now(config('app.timezone'))->startOfMonth();

        return collect(range(0, self::MONTH_OPTIONS - 1))
            ->mapWithKeys(fn (int $back) => [
                $current->subMonths($back)->format('Y-m') => $current->subMonths($back)->locale('id')->translatedFormat('F Y'),
            ])
            ->all();
    }
}
