<?php

namespace App\Filament\Resources\SpecialPrices\Pages;

use App\Filament\Resources\SpecialPrices\SpecialPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSpecialPrice extends EditRecord
{
    protected static string $resource = SpecialPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
