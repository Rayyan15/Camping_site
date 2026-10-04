<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Booking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * Hitung estimasi refund berdasarkan kebijakan yang diatur owner
     */
    public function calculateRefundAmount(Booking $booking)
    {
        if ($booking->status !== 'paid') {
            throw new Exception("Hanya booking yang sudah lunas yang bisa di-refund.");
        }

        $checkInDate = Carbon::parse($booking->check_in)->startOfDay();
        $today = now()->startOfDay();
        
        $daysBeforeCheckIn = $today->diffInDays($checkInDate, false);

        if ($daysBeforeCheckIn < 0) {
            return 0; // Sudah lewat check-in
        }

        // Cari policy yang cocok, urutkan dari min_days_before terbesar
        $policy = RefundPolicy::orderBy('min_days_before', 'desc')
            ->where('min_days_before', '<=', $daysBeforeCheckIn)
            ->first();

        $percent = $policy ? $policy->percent : 0;
        
        return ($booking->total * $percent) / 100;
    }

    /**
     * Ajukan refund oleh operator atau customer
     */
    public function requestRefund(Booking $booking, $reason, $requestedByUserId = null)
    {
        return DB::transaction(function () use ($booking, $reason, $requestedByUserId) {
            $amount = $this->calculateRefundAmount($booking);

            if ($amount <= 0) {
                throw new Exception("Booking ini tidak eligible untuk refund sesuai kebijakan.");
            }

            $refund = Refund::create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'requested',
                'requested_by' => $requestedByUserId,
            ]);

            // Update status booking agar tercatat sedang proses refund
            $booking->update(['status' => 'refunded']); // atau 'cancelled' tergantung alur

            return $refund;
        });
    }
}
