<?php

namespace App\Exceptions;

use RuntimeException;

class MenuItemUnavailableException extends RuntimeException
{
    public static function notAvailable(string $name): self
    {
        return new self(__('Menu :name sedang tidak tersedia.', ['name' => $name]));
    }

    public static function notFound(): self
    {
        return new self('Salah satu menu yang dipilih tidak ditemukan.');
    }
}
