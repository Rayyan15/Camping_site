<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payment\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function show(string $code): View|RedirectResponse
    {
        $booking = $this->findBooking($code);

        if (! $this->belongsOnCheckout($booking)) {
            return redirect()->route('booking.status', $booking->code);
        }

        return view('public.checkout', [
            'booking' => $booking,
            'holdActive' => $booking->isHoldActive(),
        ]);
    }

    public function pay(string $code, PaymentService $payments): RedirectResponse
    {
        $booking = $this->findBooking($code);

        if (! $this->belongsOnCheckout($booking)) {
            return redirect()->route('booking.status', $booking->code);
        }

        if (! $booking->isHoldActive()) {
            return redirect()->route('checkout.show', $booking->code);
        }

        return redirect()->away($payments->initiate($booking)->redirectUrl);
    }

    private function findBooking(string $code): Booking
    {
        return Booking::with([
            'customer',
            'bookingUnits.unit.unitType',
            'addons.addon',
            'orders.items.menuItem',
        ])->where('code', $code)->firstOrFail();
    }

    /**
     * Pending bookings, and holds that lapsed but were not yet released, stay on the checkout page.
     */
    private function belongsOnCheckout(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Expired], true);
    }
}
