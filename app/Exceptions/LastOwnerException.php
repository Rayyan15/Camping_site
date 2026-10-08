<?php

namespace App\Exceptions;

use RuntimeException;

class LastOwnerException extends RuntimeException
{
    public static function forChange(): self
    {
        return new self('Akun owner aktif terakhir tidak boleh dinonaktifkan atau diubah perannya.');
    }
}
