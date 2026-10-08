<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RefundStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    /**
     * Statuses where the refund is still being handled and the booking must not be refunded twice.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Requested, self::Approved];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Paid => 'Sudah Ditransfer',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Paid => 'success',
        };
    }
}
