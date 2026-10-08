<?php

namespace App\Filament\Resources\DiningSpots\Pages;

use App\Filament\Resources\DiningSpots\DiningSpotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiningSpot extends EditRecord
{
    protected static string $resource = DiningSpotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
