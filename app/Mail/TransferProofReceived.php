<?php

namespace App\Mail;

class TransferProofReceived extends BookingMail
{
    protected function subjectLine(): string
    {
        return 'Bukti transfer diterima';
    }

    protected function markdownView(): string
    {
        return 'mail.transfer-proof-received';
    }
}
