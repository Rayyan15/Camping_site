<?php

namespace App\Filament\Resources\DiningSpots;

use App\Filament\Resources\DiningSpots\Pages\CreateDiningSpot;
use App\Filament\Resources\DiningSpots\Pages\EditDiningSpot;
use App\Filament\Resources\DiningSpots\Pages\ListDiningSpots;
use App\Filament\Resources\DiningSpots\Pages\PrintQrSheet;
use App\Filament\Resources\DiningSpots\Schemas\DiningSpotForm;
use App\Filament\Resources\DiningSpots\Tables\DiningSpotsTable;
use App\Models\DiningSpot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DiningSpotResource extends Resource
{
    protected static ?string $model = DiningSpot::class;

    protected static ?string $modelLabel = 'Titik QR';

    protected static ?string $pluralModelLabel = 'Titik QR';

    protected static string|UnitEnum|null $navigationGroup = 'Menu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return DiningSpotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiningSpotsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiningSpots::route('/'),
            'create' => CreateDiningSpot::route('/create'),
            'edit' => EditDiningSpot::route('/{record}/edit'),
            'print' => PrintQrSheet::route('/cetak'),
        ];
    }
}
