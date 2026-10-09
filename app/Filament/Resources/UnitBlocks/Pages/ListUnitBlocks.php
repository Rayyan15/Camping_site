<?php

namespace App\Filament\Resources\UnitBlocks\Pages;

use App\Filament\Resources\UnitBlocks\UnitBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnitBlocks extends ListRecords
{
    protected static string $resource = UnitBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
