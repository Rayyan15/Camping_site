<?php

namespace App\Filament\Resources\SpecialPrices\Tables;

use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SpecialPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unitType.name')->label('Tipe Tenda')->sortable()->searchable(),
                ...self::dateAndPriceColumns(),
            ])
            ->filters([
                SelectFilter::make('unit_type_id')
                    ->label('Tipe Tenda')
                    ->relationship('unitType', 'name'),
                self::dateRangeFilter(),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('Belum ada harga khusus')
            ->emptyStateDescription('Tambahkan harga khusus untuk malam libur panjang atau akhir pekan ramai.')
            ->emptyStateIcon('heroicon-o-currency-dollar')
            ->striped()
            ->defaultSort('date', 'desc');
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function dateAndPriceColumns(): array
    {
        return [
            TextColumn::make('date')->label('Tanggal malam')->date('d M Y')->sortable(),
            TextColumn::make('price')
                ->label('Harga per malam')
                ->money('IDR', locale: 'id', decimalPlaces: 0)
                ->sortable(),
            TextColumn::make('note')->label('Catatan')->limit(40)->placeholder('Tanpa catatan'),
        ];
    }

    public static function dateRangeFilter(): Filter
    {
        return Filter::make('date_range')
            ->label('Rentang tanggal')
            ->schema([
                DatePicker::make('from')->label('Dari tanggal')->native(false),
                DatePicker::make('until')->label('Sampai tanggal')->native(false),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('date', '>=', $from))
                ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('date', '<=', $until)));
    }
}
