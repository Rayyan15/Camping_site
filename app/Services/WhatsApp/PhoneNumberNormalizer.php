<?php

namespace App\Services\WhatsApp;

class PhoneNumberNormalizer
{
    private const COUNTRY_CODE = '62';

    private const MIN_DIGITS = 10;

    private const MAX_DIGITS = 15;

    private const VISIBLE_TAIL = 3;

    /**
     * Indonesian mobile number in any common notation to digits-only E.164 (62812...), or null when unusable.
     */
    public function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if ($digits === '' || $digits === null) {
            return null;
        }

        $e164 = match (true) {
            str_starts_with($digits, self::COUNTRY_CODE) => $digits,
            str_starts_with($digits, '0') => self::COUNTRY_CODE.substr($digits, 1),
            str_starts_with($digits, '8') => self::COUNTRY_CODE.$digits,
            default => null,
        };

        if ($e164 === null || ! str_starts_with($e164, self::COUNTRY_CODE.'8')) {
            return null;
        }

        $length = strlen($e164);

        return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS ? $e164 : null;
    }

    /**
     * Safe to log and store: only the country code and the last digits remain.
     */
    public function mask(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return '-';
        }

        $hidden = strlen($digits) - strlen(self::COUNTRY_CODE) - self::VISIBLE_TAIL;

        if ($hidden <= 0) {
            return str_repeat('*', strlen($digits));
        }

        return substr($digits, 0, strlen(self::COUNTRY_CODE))
            .str_repeat('*', $hidden)
            .substr($digits, -self::VISIBLE_TAIL);
    }
}
