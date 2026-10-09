<?php

namespace App\Exceptions;

use RuntimeException;

class CannotDeleteReferencedRecord extends RuntimeException
{
    public static function customer(): self
    {
        return new self('Pelanggan ini punya riwayat booking sehingga tidak bisa dihapus.');
    }

    public static function unit(): self
    {
        return new self('Tenda ini punya riwayat booking sehingga tidak bisa dihapus. Ubah statusnya menjadi nonaktif.');
    }

    public static function booking(): self
    {
        return new self('Booking tidak bisa dihapus demi jejak audit. Gunakan pembatalan.');
    }
}
