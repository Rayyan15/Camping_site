<?php

namespace App\Enums;

use App\Models\Booking;

enum OccupancyCellStatus: string
{
    case Free = 'free';
    case Held = 'held';
    case Booked = 'booked';
    case Staying = 'staying';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case NeedsReview = 'needs_review';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Bebas',
            self::Held => 'Ditahan',
            self::Booked => 'Terisi',
            self::Staying => 'Menginap',
            self::Completed => 'Selesai',
            self::Blocked => 'Diblokir',
            self::NeedsReview => 'Perlu ditinjau',
            self::Unavailable => 'Nonaktif',
        };
    }

    /** Short text printed inside a cell so status never depends on color alone. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Free => 'Bebas',
            self::Held => 'Tahan',
            self::Booked => 'Terisi',
            self::Staying => 'Inap',
            self::Completed => 'Selesai',
            self::Blocked => 'Blokir',
            self::NeedsReview => 'Tinjau',
            self::Unavailable => 'Nonaktif',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Free => 'Tidak ada booking atau blokir pada malam itu',
            self::Held => 'Booking menunggu bayar, unit ditahan sementara',
            self::Booked => 'Booking lunas, tamu belum check-in',
            self::Staying => 'Tamu sudah check-in',
            self::Completed => 'Tamu sudah check-out',
            self::Blocked => 'Unit diblokir oleh pengelola',
            self::NeedsReview => 'Booking perlu ditinjau pengelola',
            self::Unavailable => 'Unit tidak aktif atau sedang maintenance',
        };
    }

    /** When two things cover the same night, the higher priority one is shown. */
    public function priority(): int
    {
        return match ($this) {
            self::NeedsReview => 7,
            self::Staying => 6,
            self::Booked => 5,
            self::Held => 4,
            self::Blocked => 3,
            self::Completed => 2,
            self::Unavailable => 1,
            self::Free => 0,
        };
    }

    /** Whether the unit counts as filled for the occupancy percentage (same rule as the dashboard). */
    public function isOccupied(): bool
    {
        return $this === self::Booked || $this === self::Staying;
    }

    public static function fromBooking(Booking $booking): ?self
    {
        return match ($booking->status) {
            BookingStatus::PendingPayment => self::Held,
            BookingStatus::Paid => self::Booked,
            BookingStatus::CheckedIn => self::Staying,
            BookingStatus::CheckedOut => self::Completed,
            BookingStatus::NeedsReview => self::NeedsReview,
            default => null,
        };
    }
}
