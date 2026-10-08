<?php

namespace App\Services;

use App\Models\Booking;

/**
 * Builds public booking codes like RCM-261008-K7M2QX. The suffix is random, so a code
 * cannot be guessed from another one, and skips look-alike characters (0/O, 1/I).
 */
class BookingCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const SUFFIX_LENGTH = 6;

    public function generate(): string
    {
        do {
            $code = 'RCM-'.now()->format('ymd').'-'.$this->randomSuffix();
        } while (Booking::where('code', $code)->exists());

        return $code;
    }

    private function randomSuffix(): string
    {
        $bytes = random_bytes(self::SUFFIX_LENGTH);
        $suffix = '';

        foreach (str_split($bytes) as $byte) {
            // The alphabet has 32 symbols, so the low five bits map evenly.
            $suffix .= self::ALPHABET[ord($byte) & 31];
        }

        return $suffix;
    }
}
