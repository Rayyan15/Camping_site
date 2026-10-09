<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user.roles'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Jabatan')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('shift.name')
                    ->label('Shift')
                    ->placeholder('Belum ditentukan')
                    ->sortable(),
                TextColumn::make('fingerprint_id')
                    ->label('ID Fingerprint')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('user.roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserForm::ROLE_LABELS[$state] ?? $state)
                    ->placeholder('Tanpa akun login'),
            ])
            ->filters([
                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('Belum ada karyawan')
            ->emptyStateDescription('Tambahkan karyawan pertama untuk mulai mencatat absensi dan penilaian.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->striped()
            ->defaultSort('name');
    }
}
