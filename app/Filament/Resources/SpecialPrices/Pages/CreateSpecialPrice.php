<?php

namespace App\Filament\Resources\SpecialPrices\Pages;

use App\Filament\Resources\SpecialPrices\SpecialPriceResource;
use App\Services\SpecialPriceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSpecialPrice extends CreateRecord
{
    protected static string $resource = SpecialPriceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SpecialPriceService::class)->saveRange(
            (int) $data['unit_type_id'],
            $data['date'],
            $this->form->getRawState()['date_until'] ?? null,
            (int) $data['price'],
            $data['note'] ?? null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
