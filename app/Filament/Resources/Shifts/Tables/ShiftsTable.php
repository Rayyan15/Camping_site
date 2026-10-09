<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Models\Shift;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Shift')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Jam Kerja')
                    ->formatStateUsing(fn (Shift $record): string => substr($record->start_time, 0, 5)
                        .' - '.substr($record->end_time, 0, 5)
                        .($record->crossesMidnight() ? ' (hari berikutnya)' : ''))
                    ->sortable(),
                TextColumn::make('late_tolerance_minutes')
                    ->label('Toleransi Terlambat')
                    ->suffix(' menit')
                    ->sortable(),
                TextColumn::make('employees_count')
                    ->label('Jumlah Karyawan')
                    ->counts('employees')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('Belum ada shift')
            ->emptyStateDescription('Tambahkan shift pertama agar karyawan bisa dijadwalkan.')
            ->emptyStateIcon('heroicon-o-clock')
            ->striped()
            ->defaultSort('start_time');
    }
}
