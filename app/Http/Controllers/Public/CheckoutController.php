<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PayCheckoutRequest;
use App\Models\Booking;
use App\Services\BookingBilling;
use App\Services\Payment\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function show(string $token, BookingBilling $billing): View|RedirectResponse
    {
        $booking = $this->findBooking($token);

        if (! $this->belongsOnCheckout($booking)) {
            return redirect()->route('booking.status', $booking->access_token);
        }

        return view('public.checkout', [
            'booking' => $booking,
            'holdActive' => $booking->isHoldActive(),
            'outstanding' => $billing->outstanding($booking),
            'downPayment' => $billing->downPaymentAmount($booking),
        ]);
    }

    public function pay(PayCheckoutRequest $request, string $token, PaymentService $payments): RedirectResponse
    {
        $booking = $this->findBooking($token);

        if (! $this->belongsOnCheckout($booking)) {
            return redirect()->route('booking.status', $booking->access_token);
        }

        if (! $booking->isHoldActive()) {
            return redirect()->route('checkout.show', $booking->access_token);
        }

        try {
            return redirect()->away($payments->initiate($booking, $request->wantsDownPayment())->redirectUrl);
        } catch (PaymentException $exception) {
            return $this->paymentFailedResponse($booking, $exception);
        }
    }

    /**
     * The exception text can carry gateway detail, so the visitor only gets fixed copy.
     */
    private function paymentFailedResponse(Booking $booking, PaymentException $exception): RedirectResponse
    {
        Log::warning('Checkout payment could not be started.', [
            'booking_code' => $booking->code,
            'already_paid' => $exception->isAlreadyPaid(),
            'reason' => $exception->getMessage(),
        ]);

        if ($exception->isAlreadyPaid()) {
            return redirect()->route('booking.status', $booking->access_token)
                ->with('success', 'Pembayaran sudah diterima. Terima kasih.');
        }

        return redirect()->route('checkout.show', $booking->access_token)
            ->with('error', 'Pembayaran belum bisa diproses saat ini. Silakan coba lagi sebentar lagi.');
    }

    private function findBooking(string $token): Booking
    {
        return Booking::with([
            'customer',
            'bookingUnits.unit.unitType',
            'addons.addon',
            'orders.items.menuItem',
        ])->byAccessToken($token)->firstOrFail();
    }

    /**
     * Pending bookings, and holds that lapsed but were not yet released, stay on the checkout page.
     */
    private function belongsOnCheckout(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Expired], true);
    }
}
