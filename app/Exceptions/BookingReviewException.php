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

    public static function proofAwaitingReview(): self
    {
        return new self('Booking ini masih punya bukti transfer yang belum diperiksa. Konfirmasi atau tolak bukti itu dulu sebelum menyelesaikan review.');
    }

    public static function notFullyPaid(int $outstanding): self
    {
        return new self('Pembayaran booking ini belum lunas, masih kurang Rp '.number_format($outstanding, 0, ',', '.').'. Catat pembayaran yang sudah masuk dulu sebelum menyelesaikan review.');
    }
}
