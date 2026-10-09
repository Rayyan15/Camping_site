<?php

namespace App\Exceptions;

/**
 * A manual (cash or transfer) payment the booking cannot accept. Messages are safe to show to staff.
 */
class ManualPaymentException extends PaymentException
{
    public static function invalidAmount(): self
    {
        return new self('Nominal pembayaran harus lebih dari nol.');
    }

    public static function exceedsOutstanding(int $outstanding): self
    {
        return new self('Nominal melebihi sisa tagihan (Rp '.number_format($outstanding, 0, ',', '.').').');
    }

    public static function bookingNotPayable(string $statusLabel): self
    {
        return new self('Pembayaran tidak dapat dicatat untuk booking berstatus '.$statusLabel.'.');
    }

    public static function partialNotAllowed(string $statusLabel): self
    {
        return new self('Booking berstatus '.$statusLabel.' hanya bisa dilunasi sekaligus, bukan dicicil.');
    }
}
