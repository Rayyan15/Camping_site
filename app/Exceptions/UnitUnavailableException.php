<?php

namespace App\Exceptions;

use RuntimeException;

class UnitUnavailableException extends RuntimeException
{
    public static function alreadyBooked(): self
    {
        return new self('Mohon maaf, beberapa unit yang Anda pilih baru saja dipesan oleh tamu lain.');
    }
}
