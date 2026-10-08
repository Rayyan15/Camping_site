<?php

namespace App\Enums;

/**
 * Kitchen queue stages. An order only ever moves one step forward.
 */
enum OrderStatus: string
{
    case Baru = 'baru';
    case Diproses = 'diproses';
    case Siap = 'siap';
    case Diantar = 'diantar';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Diproses => 'Diproses',
            self::Siap => 'Siap',
            self::Diantar => 'Diantar',
            self::Selesai => 'Selesai',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Baru => self::Diproses,
            self::Diproses => self::Siap,
            self::Siap => self::Diantar,
            self::Diantar => self::Selesai,
            self::Selesai => null,
        };
    }

    public function canMoveTo(self $target): bool
    {
        return $this->next() === $target;
    }
}
