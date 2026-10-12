<?php

namespace App\Mail;

use App\Models\Booking;

class TransferProofRejected extends BookingMail
{
    public function __construct(Booking $booking, public string $reason)
    {
        parent::__construct($booking);
    }

    protected function subjectLine(): string
    {
        return 'Bukti transfer ditolak';
    }

    protected function markdownView(): string
    {
        return 'mail.transfer-proof-rejected';
    }

    protected function extraData(): array
    {
        return ['reason' => $this->reason];
    }
}
