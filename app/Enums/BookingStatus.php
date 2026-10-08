<?php

namespace App\Enums;

enum BookingStatus: string
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
}
