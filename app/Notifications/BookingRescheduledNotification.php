<?php

namespace App\Notifications;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class BookingRescheduledNotification extends Notification
{
    public function __construct(private readonly Booking $booking, private readonly int $priceDifference) {}

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
        $dates = $this->booking->check_in->format('d M Y').' sampai '.$this->booking->check_out->format('d M Y');

        return FilamentNotification::make()
            ->title('Jadwal booking diubah')
            ->body("Booking {$this->booking->code} dipindah ke {$dates}.".$this->differenceNote())
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Buka booking')
                    ->url(BookingResource::getUrl('edit', ['record' => $this->booking])),
            ])
            ->getDatabaseMessage();
    }

    private function differenceNote(): string
    {
        $amount = number_format(abs($this->priceDifference), 0, ',', '.');

        return match (true) {
            $this->priceDifference > 0 => " Tamu masih harus membayar selisih Rp {$amount}.",
            $this->priceDifference < 0 => " Ada kelebihan bayar Rp {$amount} yang perlu dikembalikan manual.",
            default => '',
        };
    }
}
