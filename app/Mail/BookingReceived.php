<?php

namespace App\Mail;

use App\Support\BankAccount;

class BookingReceived extends BookingMail
{
    protected function subjectLine(): string
    {
        return 'Booking diterima';
    }

    protected function markdownView(): string
    {
        return 'mail.booking-received';
    }

    protected function extraData(): array
    {
        return ['bank' => BankAccount::fromConfig()];
    }
}
