<?php

namespace App\Exceptions;

/**
 * A transfer proof the booking or payment cannot take. Messages are safe to show to guests and staff.
 */
class TransferProofException extends PaymentException
{
    public static function bankAccountNotSet(): self
    {
        return new self('Rekening belum diatur, hubungi admin.');
    }

    public static function methodUnavailable(): self
    {
        return new self('Metode pembayaran ini sedang tidak tersedia. Pilih metode lain.');
    }

    public static function cashAlreadyChosen(): self
    {
        return new self('Anda sudah memilih bayar tunai di lokasi.');
    }

    public static function holdLapsed(): self
    {
        return new self('Waktu pembayaran sudah habis, tenda sudah dilepas.');
    }

    public static function notAwaitingReview(): self
    {
        return new self('Bukti transfer ini sudah diproses atau bukan bukti transfer booking.');
    }

    public static function bookingClosed(string $statusLabel): self
    {
        return new self('Booking berstatus '.$statusLabel.', bukti transfer tidak bisa dikonfirmasi. Periksa dan kembalikan dananya secara manual.');
    }
}
