<?php

namespace App\Enums;

/**
 * How a guest ordering through a QR code intends to pay.
 */
enum QrPaymentChoice: string
{
    case Cashier = 'cashier';
    case Booking = 'booking';

    public function label(): string
    {
        return match ($this) {
            self::Cashier => 'Bayar ke kasir',
            self::Booking => 'Tagihkan ke booking',
        };
    }
}
