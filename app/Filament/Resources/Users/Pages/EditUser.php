<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exceptions\LastOwnerException;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserAccountService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $role = $data['role'];
        unset($data['role']);

        try {
            return app(UserAccountService::class)->update($record, $data, $role);
        } catch (LastOwnerException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->halt();
        }
    }
}
