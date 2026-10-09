<?php

namespace App\Filament\Resources\UnitBlocks\Tables;

use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('unit.unitType'))
            ->columns([
                TextColumn::make('unit.code')->label('Tenda')->sortable()->searchable(),
                TextColumn::make('unit.unitType.name')->label('Tipe Tenda'),
                TextColumn::make('start_date')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('end_date')->label('Sampai')->date('d M Y')->sortable(),
                TextColumn::make('reason')->label('Alasan')->limit(50)->placeholder('Tanpa alasan'),
            ])
            ->filters([
                SelectFilter::make('unit_id')
                    ->label('Tenda')
                    ->relationship('unit', 'code'),
                SelectFilter::make('unit_type')
                    ->label('Tipe Tenda')
                    ->relationship('unit.unitType', 'name'),
                Filter::make('date_range')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal')->native(false),
                        DatePicker::make('until')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('end_date', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('start_date', '<=', $until))),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('Belum ada tenda yang diblokir')
            ->emptyStateDescription('Blokir tenda saat perbaikan atau perawatan agar tidak bisa dipesan.')
            ->emptyStateIcon('heroicon-o-no-symbol')
            ->striped()
            ->defaultSort('start_date', 'desc');
    }
}
