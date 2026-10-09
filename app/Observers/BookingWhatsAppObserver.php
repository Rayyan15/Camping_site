<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsappNotification;
use App\Models\Booking;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Turns booking lifecycle changes into WhatsApp jobs, so BookingService and PaymentService stay unaware of it.
 */
class BookingWhatsAppObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Booking $booking): void
    {
        $this->dispatch($booking, WhatsAppMessageType::BookingCreated);
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        $type = match ($booking->status) {
            BookingStatus::Paid => WhatsAppMessageType::PaymentConfirmed,
            BookingStatus::Cancelled => WhatsAppMessageType::BookingCancelled,
            BookingStatus::Refunded => WhatsAppMessageType::RefundApproved,
            default => null,
        };

        if ($type !== null) {
            $this->dispatch($booking, $type);
        }
    }

    private function dispatch(Booking $booking, WhatsAppMessageType $type): void
    {
        if (! config('whatsapp.enabled')) {
            return;
        }

        SendWhatsappNotification::dispatch($booking->id, $type);
    }
}
