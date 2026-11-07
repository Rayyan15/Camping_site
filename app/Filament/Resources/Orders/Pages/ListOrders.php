<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
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
            // Open orders only; a pre-order whose booking expired or was cancelled will never be served.
            'active' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotIn('status', [OrderStatus::Selesai->value, OrderStatus::Dibatalkan->value])
                    ->where(fn (Builder $open) => $open
                        ->where('source', '!=', Order::SOURCE_PREORDER)
                        ->orWhereHas('booking', fn (Builder $booking) => $booking->whereNotIn('status', BookingStatus::closed())))),
            'all' => Tab::make('Semua'),
            'transfer' => Tab::make('Cek transfer')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing($awaitingProof)
                ->badge(fn (): ?int => $this->getModel()::query()->tap($awaitingProof)->count() ?: null)
                ->badgeColor('warning'),
        ];
    }
}
