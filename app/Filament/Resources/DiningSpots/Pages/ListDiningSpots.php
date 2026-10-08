<?php

namespace App\Filament\Resources\DiningSpots\Pages;

use App\Filament\Resources\DiningSpots\DiningSpotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiningSpots extends ListRecords
{
    protected static string $resource = DiningSpotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
