<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerSpendService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        $query = static::getResource()::getEloquentQuery();

        if (auth()->user()?->can('viewSpend', Customer::class)) {
            $query = app(CustomerSpendService::class)->withSummary($query);
        }

        return $query->whereKey($key)->firstOrFail();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
