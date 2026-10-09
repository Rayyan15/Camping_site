<?php

namespace App\Services;

use App\Models\Booking;

class WhatsAppShareLink
{
    private const COUNTRY_CODE = '62';

    /**
     * Link that lets the guest pick any chat to share the booking with.
     */
    public function forBooking(Booking $booking): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->message($booking));
    }

    /**
     * Direct chat with the customer, for staff. Null when no usable phone number exists.
     */
    public function forCustomer(Booking $booking): ?string
    {
        $phone = $this->normalizePhone((string) $booking->customer?->phone);

        if ($phone === null) {
            return null;
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($this->message($booking));
    }

    public function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return match (true) {
            str_starts_with($digits, self::COUNTRY_CODE) => $digits,
            str_starts_with($digits, '0') => self::COUNTRY_CODE.substr($digits, 1),
            default => self::COUNTRY_CODE.$digits,
        };
    }

    private function message(Booking $booking): string
    {
        $booking->loadMissing('bookingUnits.unit.unitType');

        $units = $booking->bookingUnits
            ->groupBy(fn ($row) => $row->unit?->unitType?->name ?? 'Unit')
            ->map(fn ($rows, $name) => $rows->count().' x '.$name)
            ->implode(', ');

        return implode("\n", array_filter([
            'Booking '.config('site.name').' - '.$booking->code,
            'Tanggal: '.$booking->check_in->format('d/m/Y').' sampai '.$booking->check_out->format('d/m/Y'),
            $units !== '' ? 'Unit: '.$units : null,
            'Total: Rp '.number_format($booking->total, 0, ',', '.'),
            'Status booking: '.route('booking.status', $booking->access_token),
        ]));
    }
}
