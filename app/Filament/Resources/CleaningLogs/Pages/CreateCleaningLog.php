<?php

namespace App\Filament\Resources\CleaningLogs\Pages;

use App\Filament\Resources\CleaningLogs\CleaningLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCleaningLog extends CreateRecord
{
    protected static string $resource = CleaningLogResource::class;
}
