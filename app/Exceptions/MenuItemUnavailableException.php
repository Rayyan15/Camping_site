<?php

namespace App\Exceptions;

use RuntimeException;

class MenuItemUnavailableException extends RuntimeException
{
    public static function notAvailable(string $name): self
    {
        return new self(__('Menu :name sedang tidak tersedia.', ['name' => $name]));
    }

    public static function notEnoughStock(string $name, int $left): self
    {
        return new self($left === 0
            ? __('Menu :name sudah habis.', ['name' => $name])
            : __('Sisa :name tinggal :left porsi.', ['name' => $name, 'left' => $left]));
    }

    public static function notFound(): self
    {
        return new self('Salah satu menu yang dipilih tidak ditemukan.');
    }
}
