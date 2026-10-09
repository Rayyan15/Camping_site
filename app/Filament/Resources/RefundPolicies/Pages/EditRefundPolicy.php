<?php

namespace App\Filament\Resources\RefundPolicies\Pages;

use App\Filament\Resources\RefundPolicies\RefundPolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRefundPolicy extends EditRecord
{
    protected static string $resource = RefundPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
