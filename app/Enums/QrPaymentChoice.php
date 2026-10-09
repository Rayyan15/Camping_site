<?php

namespace App\Enums;

/**
 * How a guest ordering through a QR code intends to pay.
 */
enum QrPaymentChoice: string
{
    case Cashier = 'cashier';
    case Booking = 'booking';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Cashier => 'Bayar ke kasir',
            self::Booking => 'Tagihkan ke booking',
            self::Online => 'Bayar sekarang lewat QRIS atau e-wallet',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Cashier => 'Bayar saat pesanan sampai atau di kasir',
            self::Booking => 'Masuk ke tagihan booking tenda ini',
            self::Online => 'Dapur mulai menyiapkan setelah pembayaran diterima',
        };
    }
}
