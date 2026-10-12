<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Local-only stand-in for the hosted payment page. Routes are registered in routes/web.php
 * only for the local environment with the fake gateway.
 */
class FakePaymentController extends Controller
{
    public function show(string $reference): View
    {
        return view('dev.fake-pay', ['payment' => $this->findPayment($reference)]);
    }

    public function settle(Request $request, string $reference, PaymentService $payments): RedirectResponse
    {
        $payment = $this->findPayment($reference);
        $succeeded = $request->input('outcome') === 'paid';

        $payments->handleNotification([
            'order_id' => $reference,
            'transaction_status' => $succeeded ? 'settlement' : 'failure',
        ]);

        $payable = $payment->payable;

        return match (true) {
            $payable instanceof Order => redirect()->route('qr.track', [$payable->diningSpot->qr_token, $payable->code]),
            $payable instanceof Booking => redirect()->route('booking.status', $payable->access_token),
            default => abort(404),
        };
    }

    private function findPayment(string $reference): Payment
    {
        return Payment::with('payable')->where('gateway_ref', $reference)->firstOrFail();
    }
}
