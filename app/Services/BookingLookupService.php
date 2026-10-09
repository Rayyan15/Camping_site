<?php

namespace App\Services;

use App\Models\Booking;

/**
 * Finds a booking from the code a guest quotes plus a second proof (phone tail or email),
 * because the code alone is printed on invoices and chat messages and is not a credential.
 */
class BookingLookupService
{
    private const PHONE_TAIL_LENGTH = 4;

    public function find(string $code, string $proof): ?Booking
    {
        $booking = Booking::with('customer')->where('code', strtoupper(trim($code)))->first();

        if ($booking === null || $booking->customer === null) {
            return null;
        }

        return $this->proofMatches($booking, trim($proof)) ? $booking : null;
    }

    private function proofMatches(Booking $booking, string $proof): bool
    {
        $customer = $booking->customer;

        if (str_contains($proof, '@')) {
            return filled($customer->email) && hash_equals(mb_strtolower($customer->email), mb_strtolower($proof));
        }

        $tail = substr((string) preg_replace('/\D+/', '', (string) $customer->phone), -self::PHONE_TAIL_LENGTH);

        return strlen($tail) === self::PHONE_TAIL_LENGTH && hash_equals($tail, $proof);
    }
}
