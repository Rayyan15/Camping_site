<?php

namespace App\Filament\Resources\UnitTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Tipe')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->suffix(' Orang')
                    ->sortable(),
                TextColumn::make('base_price_weekday')
                    ->label('Harga Weekday')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('base_price_weekend')
                    ->label('Harga Weekend')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada data')
            ->emptyStateDescription('Data akan muncul di sini setelah ditambahkan.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}
