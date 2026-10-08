<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Exceptions\RefundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CancelBookingRequest;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookingStatusController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function show(string $code): View
    {
        $booking = $this->findBooking($code);

        return view('public.booking-status', [
            'booking' => $booking,
            'quote' => $booking->status === BookingStatus::Paid ? $this->refunds->calculate($booking) : null,
            'canCancel' => $this->refunds->isCancellable($booking) && ! $this->refunds->hasOpenRequest($booking),
            'refund' => Refund::where('booking_id', $booking->id)->latest('id')->first(),
        ]);
    }

    public function cancel(CancelBookingRequest $request, string $code): RedirectResponse
    {
        $booking = $this->findBooking($code);

        try {
            $refund = $this->refunds->cancel($booking, $request->validated('reason'));
        } catch (RefundException $e) {
            return redirect()->route('booking.status', $booking->code)->with('error', $e->getMessage());
        }

        $message = $refund
            ? 'Pengajuan pembatalan diterima. Pemilik akan meninjau dan menghubungi Anda.'
            : 'Booking dibatalkan.';

        return redirect()->route('booking.status', $booking->code)->with('success', $message);
    }

    private function findBooking(string $code): Booking
    {
        return Booking::with(['customer', 'bookingUnits.unit.unitType', 'addons.addon', 'orders'])
            ->where('code', $code)
            ->firstOrFail();
    }
}
