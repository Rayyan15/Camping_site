<?php

namespace App\Listeners;

use App\Events\BookingRescheduled;
use App\Services\GuestMailer;

class SendRescheduleConfirmationToGuest
{
    public function __construct(private readonly GuestMailer $mailer) {}

    public function handle(BookingRescheduled $event): void
    {
        $this->mailer->rescheduleConfirmed($event->booking->loadMissing('customer'));
    }
}
