<?php

namespace App\Filament\Resources\MenuCategories\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenuCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Kategori')->searchable()->weight('bold'),
                TextColumn::make('items_count')->label('Jumlah menu')->counts('items'),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([DeleteBulkAction::make()])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Belum ada kategori');
    }
}
