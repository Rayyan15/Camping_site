<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
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
            // Expired holds stay out of the default view: staff read a lapsed row next to the paid
            // one for the same tent as a double booking.
            'active' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', BookingStatus::active())),
            'needs_review' => Tab::make('Perlu ditinjau')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->needsReview())
                ->badge(Booking::needsReview()->count() ?: null)
                ->badgeColor('danger'),
            'checked_out' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', BookingStatus::CheckedOut)),
            'closed' => Tab::make('Hangus & batal')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', BookingStatus::closed())),
            'all' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }
}
