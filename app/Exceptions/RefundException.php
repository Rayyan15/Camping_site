<?php

namespace App\Exceptions;

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

    public static function invalidTransition(RefundStatus $from, RefundStatus $to): self
    {
        return new self("Status refund tidak dapat diubah dari {$from->getLabel()} ke {$to->getLabel()}.");
    }
}
