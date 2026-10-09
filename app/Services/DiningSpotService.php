<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\QrPaymentChoice;
use App\Models\Booking;
use App\Models\DiningSpot;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Owns QR tokens and the rule for which booking a tent QR may bill to.
 */
class DiningSpotService
{
    private const TOKEN_LENGTH = 32;

    private const QR_SIZE = 240;

    public function create(string $name, string $type, ?int $unitId = null): DiningSpot
    {
        return DiningSpot::create([
            'name' => $name,
            'type' => $type,
            'unit_id' => $unitId,
            'qr_token' => $this->uniqueToken(),
        ]);
    }

    /**
     * Replaces the token; the old QR stops working immediately because lookups use the token only.
     */
    public function regenerateToken(DiningSpot $spot): DiningSpot
    {
        $spot->update(['qr_token' => $this->uniqueToken()]);

        return $spot;
    }

    /**
     * SVG needs no imagick and stays sharp at any print size.
     */
    public function qrSvg(DiningSpot $spot): string
    {
        return (string) QrCode::format('svg')->size(self::QR_SIZE)->margin(1)->errorCorrection('M')->generate($spot->orderUrl());
    }

    public function findByToken(string $token): ?DiningSpot
    {
        return DiningSpot::with('unit')->where('qr_token', $token)->first();
    }

    /**
     * The booking staying in the spot's tent today (check-out day included, guests still order breakfast).
     * Resolved here so the browser can never choose which booking gets billed.
     */
    public function activeBooking(DiningSpot $spot): ?Booking
    {
        if ($spot->unit_id === null) {
            return null;
        }

        $today = now()->toDateString();

        return Booking::query()
            ->whereIn('status', [BookingStatus::Paid, BookingStatus::CheckedIn])
            ->whereHas('bookingUnits', fn ($lines) => $lines
                ->where('unit_id', $spot->unit_id)
                ->whereDate('check_in', '<=', $today)
                ->whereDate('check_out', '>=', $today))
            ->with('customer')
            // Checked-in guests first (a no-show still marked paid never wins), then the latest arrival,
            // so on a turnover day the guest who just checked in is billed, not the one leaving.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [BookingStatus::CheckedIn->value])
            ->orderByDesc('check_in')
            ->first();
    }

    /**
     * Ways a guest at this spot may pay. Billing to a booking only exists while someone stays in the tent.
     *
     * @return array<int, QrPaymentChoice>
     */
    public function paymentChoicesFor(DiningSpot $spot): array
    {
        $choices = [QrPaymentChoice::Cashier, QrPaymentChoice::Online];

        if ($this->activeBooking($spot) !== null) {
            $choices[] = QrPaymentChoice::Booking;
        }

        return $choices;
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (DiningSpot::where('qr_token', $token)->exists());

        return $token;
    }
}
