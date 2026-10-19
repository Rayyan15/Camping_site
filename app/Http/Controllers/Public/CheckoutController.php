<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\UploadTransferProofRequest;
use App\Models\Booking;
use App\Services\BookingBilling;
use App\Services\Payment\TransferProofService;
use App\Services\SettingRepository;
use App\Support\BankAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CheckoutController extends Controller
{
    public function show(string $token, BookingBilling $billing, TransferProofService $proofs, SettingRepository $settings): View|RedirectResponse
    {
        $booking = $this->findBooking($token);

        if (! $this->belongsOnCheckout($booking)) {
            return redirect()->route('booking.status', $booking->access_token);
        }

        return view('public.checkout', [
            'booking' => $booking,
            'holdActive' => $booking->isHoldActive(),
            'outstanding' => $billing->outstanding($booking),
            'bank' => BankAccount::current(),
            'proof' => $proofs->latestProof($booking),
            'methods' => $settings->guestPaymentMethods(),
            'qrisUrl' => ($path = $settings->qrisImagePath()) ? Storage::disk('public')->url($path) : null,
            'cashChosen' => $booking->payments()->cashOnArrival()->exists(),
        ]);
    }

    public function payCash(string $token, TransferProofService $proofs): RedirectResponse
    {
        $booking = $this->findBooking($token);

        if (! $this->belongsOnCheckout($booking)) {
            return redirect()->route('booking.status', $booking->access_token);
        }

        try {
            $proofs->chooseCash($booking);
        } catch (PaymentException $exception) {
            return $this->proofFailedResponse($booking, $exception);
        }

        return redirect()->route('checkout.show', $booking->access_token)
            ->with('success', 'Siap, bayar tunai saat tiba. Tenda Anda dipegang sampai akhir hari check-in.');
    }

    public function uploadProof(UploadTransferProofRequest $request, string $token, TransferProofService $proofs): RedirectResponse
    {
        $booking = $this->findBooking($token);

        if (! $this->belongsOnCheckout($booking)) {
            return redirect()->route('booking.status', $booking->access_token);
        }

        try {
            $proofs->submit($booking, $request->file('proof'), $request->wantsDownPayment(), $request->paymentMethod());
        } catch (PaymentException $exception) {
            return $this->proofFailedResponse($booking, $exception);
        }

        return redirect()->route('booking.status', $booking->access_token)
            ->with('success', 'Bukti transfer diterima. Admin akan mengonfirmasi setelah mencocokkan dengan mutasi rekening.');
    }

    private function proofFailedResponse(Booking $booking, PaymentException $exception): RedirectResponse
    {
        Log::warning('Transfer proof could not be accepted.', [
            'booking_code' => $booking->code,
            'already_paid' => $exception->isAlreadyPaid(),
            'reason' => $exception->getMessage(),
        ]);

        if ($exception->isAlreadyPaid()) {
            return redirect()->route('booking.status', $booking->access_token)
                ->with('success', 'Pembayaran sudah diterima. Terima kasih.');
        }

        return redirect()->route('checkout.show', $booking->access_token)
            ->with('error', $exception->getMessage());
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
