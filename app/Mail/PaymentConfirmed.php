<?php

namespace App\Mail;

class PaymentConfirmed extends BookingMail
{
    protected function subjectLine(): string
    {
        return 'Pembayaran dikonfirmasi';
    }

    protected function markdownView(): string
    {
        return 'mail.payment-confirmed';
    }
}
