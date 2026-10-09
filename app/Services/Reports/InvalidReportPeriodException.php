<?php

namespace App\Services\Reports;

use InvalidArgumentException;

class InvalidReportPeriodException extends InvalidArgumentException
{
    public static function reversed(): self
    {
        return new self('Tanggal akhir tidak boleh sebelum tanggal awal.');
    }

    public static function tooLong(int $maxDays): self
    {
        return new self("Rentang laporan paling panjang {$maxDays} hari.");
    }

    public static function missing(): self
    {
        return new self('Isi tanggal awal dan tanggal akhir.');
    }
}
