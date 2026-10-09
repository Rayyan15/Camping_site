<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use RuntimeException;

class RefundException extends RuntimeException
{
    public static function notCancellable(): self
    {
        return new self('Booking ini tidak dapat dibatalkan.');
    }

    public static function alreadyRequested(): self
    {
        return new self('Pengajuan pembatalan untuk booking ini sudah ada dan sedang diproses.');
    }

    public static function nothingToRefund(): self
    {
        return new self('Pembatalan ini tidak berhak atas pengembalian dana (0%), sehingga tidak dapat diajukan sebagai refund.');
    }

    public static function bookingNotRefundable(BookingStatus $status): self
    {
        return new self("Refund tidak dapat diproses karena status booking saat ini {$status->getLabel()}.");
    }

    public static function amountExceedsPaid(): self
    {
        return new self('Nominal refund melebihi dana yang sudah dibayar dan belum dikembalikan.');
    }

    public static function invalidTransition(RefundStatus $from, RefundStatus $to): self
    {
        return new self("Status refund tidak dapat diubah dari {$from->getLabel()} ke {$to->getLabel()}.");
    }
}
