<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\InvoiceService;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function download(string $token, InvoiceService $invoices): Response
    {
        $booking = Booking::byAccessToken($token)->firstOrFail();

        if (! $booking->isInvoiceable()) {
            abort(403, 'Invoice tersedia setelah pembayaran booking dikonfirmasi.');
        }

        return $invoices->render($booking)
            ->download('invoice-'.$booking->code.'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }
}
