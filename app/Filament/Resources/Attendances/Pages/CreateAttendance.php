<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Enums\AttendanceSource;
use App\Filament\Resources\Attendances\AttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = AttendanceSource::Manual->value;

        return $data;
    }
}
