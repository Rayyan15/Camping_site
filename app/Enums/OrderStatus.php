<?php

namespace App\Enums;

/**
 * Kitchen queue stages. An order only ever moves one step forward, or is cancelled while still new.
 */
enum OrderStatus: string
{
    case Baru = 'baru';
    case Diproses = 'diproses';
    case Siap = 'siap';
    case Diantar = 'diantar';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    /**
     * The forward path a guest sees on the tracking page.
     *
     * @return array<int, self>
     */
    public static function steps(): array
    {
        return [self::Baru, self::Diproses, self::Siap, self::Diantar, self::Selesai];
    }

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Diproses => 'Diproses',
            self::Siap => 'Siap',
            self::Diantar => 'Diantar',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Baru => self::Diproses,
            self::Diproses => self::Siap,
            self::Siap => self::Diantar,
            self::Diantar => self::Selesai,
            self::Selesai, self::Dibatalkan => null,
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Selesai || $this === self::Dibatalkan;
    }

    public function canMoveTo(self $target): bool
    {
        // Cancelling is only safe before the kitchen starts cooking.
        if ($target === self::Dibatalkan) {
            return $this === self::Baru;
        }

        // The kitchen has no staff to click every step, so any open order can be closed in one go.
        if ($target === self::Selesai) {
            return ! $this->isClosed();
        }

        return $this->next() === $target;
    }
}
