<?php

namespace App\Filament\Resources\SpecialPrices\RelationManagers;

use App\Filament\Resources\SpecialPrices\Schemas\SpecialPriceForm;
use App\Filament\Resources\SpecialPrices\Tables\SpecialPricesTable;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SpecialPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'specialPrices';

    protected static ?string $title = 'Harga Khusus';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(
            SpecialPriceForm::dateAndPriceFields(fn () => $this->getOwnerRecord()->getKey()),
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(SpecialPricesTable::dateAndPriceColumns())
            ->filters([SpecialPricesTable::dateRangeFilter()])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('Belum ada harga khusus')
            ->emptyStateDescription('Tambahkan harga khusus untuk tipe tenda ini pada tanggal tertentu.')
            ->defaultSort('date', 'desc');
    }
}
