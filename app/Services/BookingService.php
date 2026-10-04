<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\BookingUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class BookingService
{
    /**
     * Cek ketersediaan unit untuk tipe tertentu pada tanggal yang diminta.
     */
    public function checkAvailability($unitTypeId, $checkIn, $checkOut, $guests = 1)
    {
        // 1. Ambil semua unit dari tipe tersebut yang statusnya aktif
        $units = Unit::where('unit_type_id', $unitTypeId)
            ->where('status', 'active')
            ->get();

        // 2. Ambil ID unit yang sudah terbooking (beririsan tanggalnya)
        $bookedUnitIds = BookingUnit::whereHas('booking', function($query) {
                // Booking yang masih hold, atau sudah dibayar, atau sudah check in
                $query->whereIn('status', ['pending_payment', 'paid', 'checked_in']);
            })
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->pluck('unit_id')
            ->toArray();

        // 3. Filter unit yang tersedia
        $availableUnits = $units->filter(function($unit) use ($bookedUnitIds) {
            return !in_array($unit->id, $bookedUnitIds);
        });

        return $availableUnits;
    }

    /**
     * Membuat booking baru dengan lock table untuk mencegah double booking.
     */
    public function createBooking($customerId, $checkIn, $checkOut, $guests, $unitIds, $addonIds = [], $notes = null)
    {
        return DB::transaction(function () use ($customerId, $checkIn, $checkOut, $guests, $unitIds, $addonIds, $notes) {
            
            // Lock units dengan lockForUpdate untuk mencegah race condition (double booking)
            $lockedUnits = Unit::whereIn('id', $unitIds)->lockForUpdate()->get();

            // Pastikan unit yang dilock masih tersedia
            $bookedUnitIds = BookingUnit::whereHas('booking', function($query) {
                    $query->whereIn('status', ['pending_payment', 'paid', 'checked_in']);
                })
                ->whereIn('unit_id', $unitIds)
                ->where('check_in', '<', $checkOut)
                ->where('check_out', '>', $checkIn)
                ->pluck('unit_id')
                ->toArray();

            if (!empty($bookedUnitIds)) {
                throw new Exception("Mohon maaf, beberapa unit yang Anda pilih baru saja dipesan oleh tamu lain.");
            }

            $checkInDate = Carbon::parse($checkIn);
            $checkOutDate = Carbon::parse($checkOut);
            $nights = $checkInDate->diffInDays($checkOutDate);
            
            // Hitung harga dll (Asumsi disederhanakan untuk contoh)
            $subtotal = 0;
            
            // Buat Booking
            $booking = Booking::create([
                'code' => 'RCM-' . now()->format('ymd') . '-' . rand(1000, 9999),
                'customer_id' => $customerId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests,
                'status' => 'pending_payment',
                'hold_expires_at' => now()->addMinutes(config('app.booking_hold_minutes', 15)),
                'subtotal' => 0, // Akan diupdate nanti
                'tax' => 0,
                'total' => 0,
                'notes' => $notes,
            ]);

            // Insert Booking Unit
            foreach ($lockedUnits as $unit) {
                // TODO: Ambil harga yang benar dari SpecialPrice atau base_price UnitType
                $pricePerNight = $unit->unit_type->base_price_weekday ?? 0; 
                $unitSubtotal = $pricePerNight * $nights;
                $subtotal += $unitSubtotal;

                BookingUnit::create([
                    'booking_id' => $booking->id,
                    'unit_id' => $unit->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'price_per_night' => $pricePerNight,
                    'nights' => $nights,
                    'subtotal' => $unitSubtotal,
                ]);
            }

            // Hitung pajak & update
            $tax = $subtotal * 0.11; // Contoh pajak 11%
            $booking->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax
            ]);

            return $booking;
        });
    }
}
