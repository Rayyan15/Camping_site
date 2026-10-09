<?php

namespace App\Filament\Resources\UnitBlocks\Pages;

use App\Exceptions\UnitBlockConflictException;
use App\Filament\Resources\UnitBlocks\UnitBlockResource;
use App\Services\UnitBlockService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUnitBlock extends EditRecord
{
    protected static string $resource = UnitBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UnitBlockService::class)->update($record, $data);
        } catch (UnitBlockConflictException $e) {
            throw ValidationException::withMessages(['data.unit_id' => $e->getMessage()]);
        }
    }
}
