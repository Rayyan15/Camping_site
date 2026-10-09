<?php

namespace App\Filament\Resources\SpecialPrices;

use App\Filament\Resources\SpecialPrices\Pages\CreateSpecialPrice;
use App\Filament\Resources\SpecialPrices\Pages\EditSpecialPrice;
use App\Filament\Resources\SpecialPrices\Pages\ListSpecialPrices;
use App\Filament\Resources\SpecialPrices\Schemas\SpecialPriceForm;
use App\Filament\Resources\SpecialPrices\Tables\SpecialPricesTable;
use App\Models\SpecialPrice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\View\ComponentAttributeBag;

class SpecialPriceResource extends Resource
{
    protected static ?string $model = SpecialPrice::class;

    protected static ?string $modelLabel = 'Harga Khusus';

    protected static ?string $pluralModelLabel = 'Harga Khusus';

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Tenda';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getNavigationIcon(): string|ComponentAttributeBag
    {
        return 'heroicon-o-currency-dollar';
    }

    public static function form(Schema $schema): Schema
    {
        return SpecialPriceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpecialPricesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecialPrices::route('/'),
            'create' => CreateSpecialPrice::route('/create'),
            'edit' => EditSpecialPrice::route('/{record}/edit'),
        ];
    }
}
