<?php

namespace App\Exceptions;

use RuntimeException;

class BookingRescheduleException extends RuntimeException
{
    public static function notEligibleStatus(): self
    {
        return new self('Hanya booking yang sudah lunas dan belum check-in yang dapat dijadwal ulang.');
    }

    public static function alreadyUsed(): self
    {
        return new self('Jadwal booking ini sudah pernah diubah. Perubahan jadwal hanya bisa dilakukan satu kali.');
    }

    public static function tooLate(int $minDays): self
    {
        return new self("Jadwal ulang hanya bisa dilakukan paling lambat {$minDays} hari sebelum check-in.");
    }

    public static function refundPending(): self
    {
        return new self('Booking ini punya pengajuan pembatalan yang sedang diproses, jadi jadwalnya tidak bisa diubah.');
    }

    public static function sameDates(): self
    {
        return new self('Tanggal baru sama dengan tanggal booking saat ini.');
    }

    public static function datesUnavailable(): self
    {
        return new self('Tenda untuk tanggal baru tidak tersedia. Pilih tanggal lain.');
    }

    public static function priceIncreased(int $difference): self
    {
        $formatted = number_format($difference, 0, ',', '.');

        return new self("Harga di tanggal baru lebih tinggi Rp {$formatted}. Hubungi admin untuk memindah jadwal dan membayar selisihnya.");
    }

    public static function preordersOutsideStay(): self
    {
        return new self('Ada pesanan makanan yang jatuh di luar tanggal baru. Hubungi admin untuk mengatur ulang pesanan sebelum memindah jadwal.');
    }
}
