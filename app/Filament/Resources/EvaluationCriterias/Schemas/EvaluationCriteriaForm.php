<?php

namespace App\Filament\Resources\EvaluationCriterias\Schemas;

use App\Models\EvaluationCriteria;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EvaluationCriteriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kriteria')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kriteria')
                            ->placeholder('Contoh: Kedisiplinan')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),
                        TextInput::make('weight')
                            ->label('Bobot')
                            ->numeric()
                            ->integer()
                            ->minValue(EvaluationCriteria::MIN_WEIGHT)
                            ->maxValue(EvaluationCriteria::MAX_WEIGHT)
                            ->required()
                            ->rules([
                                fn (Get $get, ?Model $record): Closure => self::totalWeightRule((bool) $get('is_active'), $record),
                            ])
                            ->helperText('Bobot 1 sampai 100. Total bobot semua kriteria aktif tidak boleh melebihi 100.'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Kriteria nonaktif tidak muncul di penilaian baru dan tidak dihitung ke total bobot.'),
                        Toggle::make('is_attendance')
                            ->label('Skor kehadiran otomatis')
                            ->default(false)
                            ->helperText('Skor awal kriteria ini diisi dari data absensi bila tersedia. Owner tetap bisa mengubahnya.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function totalWeightRule(bool $isActive, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($isActive, $record): void {
            if (! $isActive) {
                return;
            }

            $total = EvaluationCriteria::activeWeightTotal($record?->getKey()) + (int) $value;

            if ($total > EvaluationCriteria::TOTAL_WEIGHT) {
                $fail("Total bobot kriteria aktif menjadi {$total}, melebihi batas ".EvaluationCriteria::TOTAL_WEIGHT.'.');
            }
        };
    }
}
