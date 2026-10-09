<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidCleaningLogReviewException extends RuntimeException
{
    public static function alreadyReviewed(): self
    {
        return new self('Laporan ini sudah diperiksa.');
    }
}
