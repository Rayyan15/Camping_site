<?php

namespace App\Notifications;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Stored in the database channel in the shape Filament's bell renders. */
class BookingNeedsReviewNotification extends Notification
{
    public function __construct(private readonly Booking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Booking perlu ditinjau')
            ->body("Booking {$this->booking->code} sudah dibayar tetapi unitnya sudah terambil. Pilih pindah unit atau refund penuh.")
            ->warning()
            ->actions([
                Action::make('review')
                    ->label('Tinjau booking')
                    ->url(BookingResource::getUrl('edit', ['record' => $this->booking])),
            ])
            ->getDatabaseMessage();
    }
}
