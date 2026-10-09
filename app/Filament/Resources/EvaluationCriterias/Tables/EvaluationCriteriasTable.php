<?php

namespace App\Filament\Resources\EvaluationCriterias\Tables;

use App\Models\EvaluationCriteria;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EvaluationCriteriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading(fn (): string => 'Total bobot aktif: '.EvaluationCriteria::activeWeightTotal().' dari '.EvaluationCriteria::TOTAL_WEIGHT)
            ->description(fn (): ?string => self::weightWarning())
            ->columns([
                TextColumn::make('name')
                    ->label('Kriteria')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('weight')
                    ->label('Bobot')
                    ->sortable(),
                IconColumn::make('is_attendance')
                    ->label('Dari absensi')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('scores_count')
                    ->label('Dipakai di penilaian')
                    ->counts('scores'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('Belum ada kriteria penilaian')
            ->emptyStateDescription('Tambahkan kriteria seperti kedisiplinan atau kerja sama, lalu atur bobotnya hingga total 100.')
            ->emptyStateIcon('heroicon-o-scale')
            ->striped()
            ->defaultSort('weight', 'desc');
    }

    private static function weightWarning(): ?string
    {
        $total = EvaluationCriteria::activeWeightTotal();

        if ($total === EvaluationCriteria::TOTAL_WEIGHT) {
            return null;
        }

        return $total < EvaluationCriteria::TOTAL_WEIGHT
            ? 'Total belum 100. Skor akhir tetap dihitung proporsional, tetapi sebaiknya lengkapi bobotnya.'
            : 'Total melebihi 100. Kurangi bobot salah satu kriteria aktif.';
    }
}
