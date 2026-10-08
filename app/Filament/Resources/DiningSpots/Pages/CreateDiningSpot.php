<?php

namespace App\Filament\Resources\DiningSpots\Pages;

use App\Filament\Resources\DiningSpots\DiningSpotResource;
use App\Services\DiningSpotService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDiningSpot extends CreateRecord
{
    protected static string $resource = DiningSpotResource::class;

    // The service owns token generation, so the QR token is never typed in by hand.
    protected function handleRecordCreation(array $data): Model
    {
        return app(DiningSpotService::class)->create($data['name'], $data['type'], $data['unit_id'] ?? null);
    }
}
