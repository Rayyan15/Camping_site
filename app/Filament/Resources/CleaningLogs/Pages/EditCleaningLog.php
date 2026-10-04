<?php

namespace App\Filament\Resources\CleaningLogs\Pages;

use App\Filament\Resources\CleaningLogs\CleaningLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCleaningLog extends EditRecord
{
    protected static string $resource = CleaningLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
