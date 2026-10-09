<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Exceptions\RefundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CancelBookingRequest;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\CancellationOutcome;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookingStatusController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function show(string $token): View
    {
        $booking = $this->findBooking($token);

        return view('public.booking-status', [
            'booking' => $booking,
            'quote' => $booking->status === BookingStatus::Paid ? $this->refunds->calculate($booking) : null,
            'canCancel' => $this->refunds->isCancellable($booking) && ! $this->refunds->hasOpenRequest($booking),
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

    private function findBooking(string $token): Booking
    {
        return Booking::with(['customer', 'bookingUnits.unit.unitType', 'addons.addon', 'orders'])
            ->byAccessToken($token)
            ->firstOrFail();
    }
}
