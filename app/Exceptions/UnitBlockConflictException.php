<?php

namespace App\Exceptions;

use RuntimeException;

class UnitBlockConflictException extends RuntimeException
{
    /**
     * @param  array<int, string>  $bookingCodes
     */
    public static function overlapsBookings(array $bookingCodes): self
    {
        return new self('Blokir bentrok dengan booking aktif: '.implode(', ', $bookingCodes).'.');
    }
}
