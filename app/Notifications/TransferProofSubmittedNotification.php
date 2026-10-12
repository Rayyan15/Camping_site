<?php

namespace App\Notifications;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Stored in the database channel in the shape Filament's bell renders. */
class TransferProofSubmittedNotification extends Notification
{
    public function __construct(private readonly Payment $payment) {}

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
        $code = $this->payment->payable?->code ?? '-';
        $amount = number_format($this->payment->amount, 0, ',', '.');

        return FilamentNotification::make()
            ->title('Bukti transfer menunggu')
            ->body("Booking {$code} mengirim bukti transfer Rp {$amount}. Cocokkan dengan mutasi rekening, lalu konfirmasi atau tolak.")
            ->warning()
            ->actions([
                Action::make('review')
                    ->label('Periksa bukti')
                    ->url(PaymentResource::getUrl('view', ['record' => $this->payment])),
            ])
            ->getDatabaseMessage();
    }
}
