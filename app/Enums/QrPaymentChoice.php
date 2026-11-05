<?php

namespace App\Enums;

/**
 * How a guest ordering through a QR code intends to pay.
 */
enum QrPaymentChoice: string
{
    case Cashier = 'cashier';
    case Booking = 'booking';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cashier => 'Bayar ke kasir',
            self::Booking => 'Tagihkan ke booking',
            self::Transfer => 'Transfer bank',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Cashier => 'Bayar saat pesanan sampai atau di kasir',
            self::Booking => 'Masuk ke tagihan booking tenda ini',
            self::Transfer => 'Transfer ke rekening lalu unggah bukti. Pesanan dimasak setelah dapur mengecek.',
        };
    }
}
