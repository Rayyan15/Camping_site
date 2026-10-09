<?php

namespace App\Enums;

enum ReportType: string
{
    case Booking = 'booking';
    case Occupancy = 'okupansi';
    case TopMenu = 'menu-terlaris';
    case Refund = 'refund';
    case PaymentMethod = 'pembayaran';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking',
            self::Occupancy => 'Okupansi per tipe unit',
            self::TopMenu => 'Menu terlaris',
            self::Refund => 'Refund',
            self::PaymentMethod => 'Pembayaran per metode',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
