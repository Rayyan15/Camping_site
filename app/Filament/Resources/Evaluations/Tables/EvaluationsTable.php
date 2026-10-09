<?php

namespace App\Filament\Resources\Evaluations\Tables;

use App\Filament\Resources\Evaluations\Schemas\EvaluationForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EvaluationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Bulan')
                    ->formatStateUsing(fn (string $state): string => EvaluationForm::parsePeriod(substr($state, 0, 7))
                        ?->locale('id')->translatedFormat('F Y') ?? $state)
                    ->sortable(),
                TextColumn::make('total_score')
                    ->label('Total Skor')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('evaluator.name')
                    ->label('Dinilai oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Bulan')
                    ->options(fn (): array => EvaluationForm::periodOptions()),
                SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('Belum ada penilaian')
            ->emptyStateDescription('Buat penilaian bulanan pertama setelah kriteria dan karyawan terisi.')
            ->emptyStateIcon('heroicon-o-star')
            ->striped()
            ->defaultSort('period', 'desc');
    }
}
