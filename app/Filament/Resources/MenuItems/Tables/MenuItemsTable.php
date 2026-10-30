<?php

namespace App\Filament\Resources\MenuItems\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('Foto')->disk('public')->square(),
                TextColumn::make('name')->label('Menu')->searchable()->weight('bold'),
                TextColumn::make('category.name')->label('Kategori')->sortable(),
                TextColumn::make('price')->label('Harga')->money('IDR', locale: 'id', decimalPlaces: 0)->sortable(),
                ToggleColumn::make('is_available')
                    ->label('Tersedia')
                    ->disabled(fn () => ! (auth()->user()?->can('manage_menu') || auth()->user()?->can('toggle_menu_availability'))),
                TextColumn::make('sort_order')->label('Urutan')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([DeleteBulkAction::make()])
            ->defaultSort('category_id')
            // Guests see items ordered by sort_order inside each category, so dragging here sets that order.
            ->reorderable('sort_order', fn (): bool => (bool) auth()->user()?->can('manage_menu'))
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering) => $action
                ->button()
                ->label($isReordering ? 'Selesai mengurutkan' : 'Ubah urutan menu'))
            ->emptyStateHeading('Belum ada menu');
    }
}
