<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exceptions\LastOwnerException;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = self::asUser($this->getRecord())->roles->first()?->name;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $role = $data['role'];
        unset($data['role']);

        try {
            return app(UserAccountService::class)->update(self::asUser($record), $data, $role);
        } catch (LastOwnerException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->halt();

            return $record;
        }
    }

    private static function asUser(Model $record): User
    {
        if (! $record instanceof User) {
            throw new LogicException('EditUser can only edit User records.');
        }

        return $record;
    }
}
