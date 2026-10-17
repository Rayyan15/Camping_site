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

    /** The question each report answers, shown under its name on the report picker. */
    public function question(): string
    {
        return match ($this) {
            self::Booking => 'Tamu yang check-in di periode ini, dengan status dan pembayarannya.',
            self::Occupancy => 'Malam terisi dibanding malam tersedia, per tipe tenda.',
            self::TopMenu => 'Menu yang paling banyak terjual dan omzetnya.',
            self::Refund => 'Pengajuan pengembalian dana dan statusnya.',
            self::PaymentMethod => 'Uang masuk dan keluar, dikelompokkan per metode bayar.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Booking => 'heroicon-o-calendar-days',
            self::Occupancy => 'heroicon-o-home-modern',
            self::TopMenu => 'heroicon-o-shopping-bag',
            self::Refund => 'heroicon-o-arrow-uturn-left',
            self::PaymentMethod => 'heroicon-o-credit-card',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
