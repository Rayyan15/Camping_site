<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use Carbon\CarbonInterface;
use RuntimeException;

class InvalidBookingTransitionException extends RuntimeException
{
    public static function between(BookingStatus $from, BookingStatus $to): self
    {
        return new self("Booking berstatus {$from->getLabel()} tidak bisa diubah menjadi {$to->getLabel()}.");
    }

    public static function beforeCheckInDate(CarbonInterface $checkIn): self
    {
        return new self('Check-in baru bisa dilakukan mulai '.$checkIn->locale('id')->translatedFormat('j F Y').'.');
    }

    public static function holdsPayment(): self
    {
        return new self('Booking ini sudah menerima pembayaran. Batalkan lewat pengajuan refund supaya dananya tercatat.');
    }
}
