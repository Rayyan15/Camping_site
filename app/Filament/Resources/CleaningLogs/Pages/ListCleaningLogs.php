<?php

namespace App\Filament\Resources\CleaningLogs\Pages;

use App\Filament\Resources\CleaningLogs\CleaningLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCleaningLogs extends ListRecords
{
    protected static string $resource = CleaningLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
