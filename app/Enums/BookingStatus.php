<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case NeedsReview = 'needs_review';

    /**
     * Statuses that occupy a unit, ignoring the hold expiry of pending bookings.
     *
     * @return array<int, self>
     */
    public static function occupying(): array
    {
        return [self::Paid, self::CheckedIn, self::NeedsReview];
    }

    /**
     * Bookings staff still have to act on: waiting for payment or holding a unit.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::PendingPayment, ...self::occupying()];
    }

    /**
     * Bookings that no longer hold a unit and never will without staff intervention.
     *
     * @return array<int, self>
     */
    public static function closed(): array
    {
        return [self::Expired, self::Cancelled, self::Refunded];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Bayar',
            self::Paid => 'Lunas',
            self::CheckedIn => 'Check-in',
            self::CheckedOut => 'Check-out',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
            self::Refunded => 'Dikembalikan',
            self::NeedsReview => 'Perlu Ditinjau',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Paid, self::CheckedIn => 'success',
            self::CheckedOut => 'info',
            self::Cancelled, self::Expired, self::Refunded => 'danger',
            self::NeedsReview => 'gray',
        };
    }
}
