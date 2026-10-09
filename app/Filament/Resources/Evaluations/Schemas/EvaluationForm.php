<?php

namespace App\Filament\Resources\Evaluations\Schemas;

use App\Contracts\AttendanceScoreSource;
use App\Models\Employee;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationScore;
use App\Services\EvaluationScoreCalculator;
use Carbon\Carbon;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class EvaluationForm
{
    private const PERIOD_FORMAT = 'Y-m';

    private const SELECTABLE_MONTHS = 24;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periode Penilaian')
                    ->columns(2)
                    ->schema([
                        Select::make('employee_id')
                            ->label('Karyawan')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshScoreRows($get, $set)),
                        Select::make('period')
                            ->label('Bulan')
                            ->options(fn (): array => self::periodOptions())
                            ->default(fn (): string => self::now()->format(self::PERIOD_FORMAT))
                            ->required()
                            ->disabledOn('edit')
                            ->live()
                            ->unique(
                                table: 'evaluations',
                                column: 'period',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('employee_id', $get('employee_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Karyawan ini sudah dinilai untuk bulan tersebut.',
                            ])
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshScoreRows($get, $set)),
                    ]),
                Section::make('Skor per Kriteria')
                    ->description('Isi skor 0 sampai 100 untuk tiap kriteria. Total dihitung otomatis dari bobot.')
                    ->schema([
                        Repeater::make('scores')
                            ->hiddenLabel()
                            ->relationship('scores')
                            ->default(fn (): array => self::scoreRows())
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(2)
                            ->live()
                            ->schema([
                                Select::make('criteria_id')
                                    ->label('Kriteria')
                                    ->options(fn (): array => self::criteriaOptions())
                                    ->required()
                                    ->distinct()
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('score')
                                    ->label('Skor')
                                    ->numeric()
                                    ->minValue(EvaluationScore::MIN_SCORE)
                                    ->maxValue(EvaluationScore::MAX_SCORE)
                                    ->required()
                                    ->live(onBlur: true),
                            ]),
                        Text::make(fn (Get $get): string => 'Total skor: '.number_format(self::previewTotal($get('scores') ?? []), EvaluationScoreCalculator::DECIMALS, ',', '.'))
                            ->weight('bold'),
                    ]),
                Section::make('Catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan Owner')
                            ->rows(3)
                            ->default(null),
                    ]),
            ]);
    }

    /** @return array<string, string> */
    public static function periodOptions(): array
    {
        $options = [];
        $month = self::now()->startOfMonth();

        for ($i = 0; $i < self::SELECTABLE_MONTHS; $i++) {
            $options[$month->format(self::PERIOD_FORMAT)] = $month->locale('id')->translatedFormat('F Y');
            $month = $month->subMonthNoOverflow();
        }

        return $options;
    }

    /**
     * One row per active criterion. The attendance criterion is prefilled from the
     * configured source when it has data for the chosen employee and month (FR-64).
     *
     * @return list<array{criteria_id: int, score: int|null}>
     */
    public static function scoreRows(?int $employeeId = null, ?string $period = null): array
    {
        $employee = $employeeId ? Employee::find($employeeId) : null;
        $month = self::parsePeriod($period);
        $source = app(AttendanceScoreSource::class);

        return EvaluationCriteria::query()->active()->orderByDesc('weight')->get()
            ->map(fn (EvaluationCriteria $criteria): array => [
                'criteria_id' => $criteria->id,
                'score' => ($criteria->is_attendance && $employee && $month) ? $source->scoreFor($employee, $month) : null,
            ])
            ->all();
    }

    private static function refreshScoreRows(Get $get, Set $set): void
    {
        $employeeId = $get('employee_id');
        $period = $get('period');

        if ($employeeId && $period) {
            $set('scores', self::scoreRows((int) $employeeId, $period));
        }
    }

    /** @return array<int, string> */
    private static function criteriaOptions(): array
    {
        return EvaluationCriteria::query()->orderByDesc('weight')->get()
            ->mapWithKeys(fn (EvaluationCriteria $criteria): array => [$criteria->id => "{$criteria->name} (bobot {$criteria->weight})"])
            ->all();
    }

    /** @param array<array-key, array{criteria_id?: mixed, score?: mixed}> $rows */
    private static function previewTotal(array $rows): float
    {
        $weights = EvaluationCriteria::query()->pluck('weight', 'id');

        return app(EvaluationScoreCalculator::class)->calculate(
            collect($rows)->map(fn (array $row): array => [
                'weight' => $weights[$row['criteria_id'] ?? null] ?? null,
                'score' => $row['score'] ?? null,
            ])
        );
    }

    public static function parsePeriod(?string $period): ?Carbon
    {
        if (! $period || ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return null;
        }

        return Carbon::createFromFormat(self::PERIOD_FORMAT.'-d', $period.'-01', 'Asia/Jakarta')->startOfDay();
    }

    private static function now(): Carbon
    {
        return Carbon::now('Asia/Jakarta');
    }
}
