<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'needs_review' => Tab::make('Perlu ditinjau')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->needsReview())
                ->badge(Booking::needsReview()->count() ?: null)
                ->badgeColor('danger'),
        ];
    }
}
