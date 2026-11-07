<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $awaitingProof = fn (Builder $query) => $query->awaitingTransferCheck();

        return [
            'all' => Tab::make('Semua'),
            'transfer' => Tab::make('Cek transfer')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing($awaitingProof)
                ->badge(fn (): ?int => $this->getModel()::query()->tap($awaitingProof)->count() ?: null)
                ->badgeColor('warning'),
        ];
    }
}
