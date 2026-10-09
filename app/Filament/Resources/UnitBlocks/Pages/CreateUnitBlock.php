<?php

namespace App\Filament\Resources\UnitBlocks\Pages;

use App\Exceptions\UnitBlockConflictException;
use App\Filament\Resources\UnitBlocks\UnitBlockResource;
use App\Services\UnitBlockService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUnitBlock extends CreateRecord
{
    protected static string $resource = UnitBlockResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(UnitBlockService::class)->create($data);
        } catch (UnitBlockConflictException $e) {
            throw ValidationException::withMessages(['data.unit_id' => $e->getMessage()]);
        }
    }
}
