<?php

namespace App\Mail;

use App\Models\Booking;

/**
 * Amounts are taken when the reschedule commits, so a payment recorded before the queue runs
 * does not change what the guest was told the new dates cost.
 */
class RescheduleConfirmed extends BookingMail
{
    public function __construct(Booking $booking, public int $outstanding, public int $credit)
    {
        parent::__construct($booking);
    }

    protected function subjectLine(): string
    {
        return 'Jadwal booking diubah';
    }

    protected function markdownView(): string
    {
        return 'mail.reschedule-confirmed';
    }

    protected function extraData(): array
    {
        return ['outstanding' => $this->outstanding, 'credit' => $this->credit];
    }
}
