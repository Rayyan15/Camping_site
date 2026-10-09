<?php

namespace App\Filament\Resources\SpecialPrices\Pages;

use App\Filament\Resources\SpecialPrices\SpecialPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpecialPrices extends ListRecords
{
    protected static string $resource = SpecialPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
