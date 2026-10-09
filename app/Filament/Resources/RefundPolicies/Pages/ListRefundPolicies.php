<?php

namespace App\Filament\Resources\RefundPolicies\Pages;

use App\Filament\Resources\RefundPolicies\RefundPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRefundPolicies extends ListRecords
{
    protected static string $resource = RefundPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
