<?php

namespace App\Filament\Resources\UnitBlocks;

use App\Filament\Resources\UnitBlocks\Pages\CreateUnitBlock;
use App\Filament\Resources\UnitBlocks\Pages\EditUnitBlock;
use App\Filament\Resources\UnitBlocks\Pages\ListUnitBlocks;
use App\Filament\Resources\UnitBlocks\Schemas\UnitBlockForm;
use App\Filament\Resources\UnitBlocks\Tables\UnitBlocksTable;
use App\Models\UnitBlock;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\View\ComponentAttributeBag;

class UnitBlockResource extends Resource
{
    protected static ?string $model = UnitBlock::class;

    protected static ?string $modelLabel = 'Blokir Tenda';

    protected static ?string $pluralModelLabel = 'Blokir Tenda';

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Tenda';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationIcon(): string|ComponentAttributeBag
    {
        return 'heroicon-o-no-symbol';
    }

    public static function form(Schema $schema): Schema
    {
        return UnitBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitBlocksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitBlocks::route('/'),
            'create' => CreateUnitBlock::route('/create'),
            'edit' => EditUnitBlock::route('/{record}/edit'),
        ];
    }
}
