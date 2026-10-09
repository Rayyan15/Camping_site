<?php

namespace App\Exceptions;

use RuntimeException;

class BookingReviewException extends RuntimeException
{
    public static function notInReview(): self
    {
        return new self('Booking ini tidak sedang berstatus Perlu Ditinjau.');
    }

    public static function noFreeUnit(): self
    {
        return new self('Tidak ada unit bertipe sama yang kosong pada tanggal menginap tamu. Pilih opsi batalkan dengan refund penuh.');
    }

    public static function notAuthorized(): self
    {
        return new self('Hanya pemilik yang dapat menyelesaikan review booking.');
    }
}
