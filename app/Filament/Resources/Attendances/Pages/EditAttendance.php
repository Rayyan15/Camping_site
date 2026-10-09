<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Enums\AttendanceSource;
use App\Filament\Resources\Attendances\AttendanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendance extends EditRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** An owner edit makes the row manual, so the next fingerprint sync does not overwrite it. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['source'] = AttendanceSource::Manual->value;

        return $data;
    }
}
