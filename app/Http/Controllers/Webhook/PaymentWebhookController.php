<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
// use App\Services\PaymentService;

class PaymentWebhookController extends Controller
{
    /**
     * Handle webhook dari Payment Gateway (Midtrans/Xendit)
     */
    public function handle(Request $request)
    {
        // 1. Verifikasi signature / validasi sumber asli
        $payload = $request->all();
        
        // Contoh validasi signature Midtrans
        // $signatureKey = hash("sha512", $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . config('services.midtrans.server_key'));
        // if ($signatureKey !== $payload['signature_key']) {
        //     return response()->json(['message' => 'Invalid signature'], 403);
        // }

        Log::info('Webhook Payment Gateway Diterima', $payload);

        // 2. Ambil referensi order/booking dan cek status
        // $gatewayRef = $payload['transaction_id'];
        // $orderId = $payload['order_id'];
        // $status = $payload['transaction_status'];

        // 3. Proses berdasarkan status (settlement, pending, expire, dll) menggunakan PaymentService
        // if ($status === 'settlement' || $status === 'capture') {
        //     app(PaymentService::class)->markAsPaid($orderId, $gatewayRef, $payload);
        // } elseif ($status === 'expire' || $status === 'cancel' || $status === 'deny') {
        //     app(PaymentService::class)->markAsFailed($orderId, $gatewayRef, $payload);
        // }

        // Wajib return 200 OK agar gateway tidak mengulang pengiriman
        return response()->json(['status' => 'success']);
    }
}
