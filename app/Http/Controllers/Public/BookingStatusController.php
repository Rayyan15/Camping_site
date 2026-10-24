<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRescheduleException;
use App\Exceptions\RefundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CancelBookingRequest;
use App\Http\Requests\Public\RescheduleBookingRequest;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\BookingBilling;
use App\Services\BookingRescheduleService;
use App\Services\CancellationOutcome;
use App\Services\Payment\TransferProofService;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookingStatusController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly BookingRescheduleService $reschedules,
        private readonly TransferProofService $proofs,
        private readonly BookingBilling $billing,
    ) {}

    public function show(string $token): View
    {
        $booking = $this->findBooking($token);

        return view('public.booking-status', [
            'booking' => $booking,
            'quote' => $booking->status === BookingStatus::Paid ? $this->refunds->calculate($booking) : null,
            'canCancel' => $this->refunds->isCancellable($booking) && ! $this->refunds->hasOpenRequest($booking),
            'rescheduleBlockedReason' => $booking->status === BookingStatus::Paid ? $this->reschedules->blockedReason($booking) : null,
            'proof' => $this->proofs->latestProof($booking),
            'outstanding' => $booking->status === BookingStatus::Paid ? $this->billing->outstanding($booking) : 0,
            'refund' => Refund::where('booking_id', $booking->id)->latest('id')->first(),
        ]);
    }

    public function cancel(CancelBookingRequest $request, string $token): RedirectResponse
    {
        $booking = $this->findBooking($token);

        try {
            $result = $this->refunds->cancelWithOutcome($booking, $request->validated('reason'));
        } catch (RefundException $e) {
            return redirect()->route('booking.status', $booking->access_token)->with('error', $e->getMessage());
        }

        $message = match ($result->outcome) {
            CancellationOutcome::RefundRequested => 'Pengajuan pembatalan diterima. Pemilik akan meninjau dan menghubungi Anda.',
            CancellationOutcome::CancelledWithoutRefund => 'Booking dibatalkan. Sesuai kebijakan, pembatalan ini tidak mendapat pengembalian dana.',
            CancellationOutcome::Cancelled => 'Booking dibatalkan.',
        };

        return redirect()->route('booking.status', $booking->access_token)->with('success', $message);
    }

    public function reschedule(RescheduleBookingRequest $request, string $token): RedirectResponse
    {
        $booking = $this->findBooking($token);

        try {
            $result = $this->reschedules->reschedule($booking, $request->validated('check_in'), $request->validated('check_out'));
        } catch (BookingRescheduleException $e) {
            return redirect()->route('booking.status', $booking->access_token)->with('error', $e->getMessage());
        }

        $message = $result->priceDifference < 0
            ? 'Jadwal booking sudah diubah. Harga di tanggal baru lebih rendah, kelebihan bayar akan dikembalikan oleh admin.'
            : 'Jadwal booking sudah diubah.';

        return redirect()->route('booking.status', $booking->access_token)->with('success', $message);
    }

    private function findBooking(string $token): Booking
    {
        return Booking::with(['customer', 'bookingUnits.unit.unitType', 'addons.addon', 'orders'])
            ->byAccessToken($token)
            ->firstOrFail();
    }
}
