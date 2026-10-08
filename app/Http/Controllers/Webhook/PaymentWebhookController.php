<?php

namespace App\Http\Controllers\Webhook;

use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\UnknownPaymentException;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, PaymentService $payments): JsonResponse
    {
        try {
            $payments->handleNotification($request->all());
        } catch (InvalidPaymentSignatureException) {
            return response()->json(['status' => 'invalid_signature'], 403);
        } catch (UnknownPaymentException) {
            Log::warning('Payment notification for an unknown reference', [
                'order_id' => $request->input('order_id'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'unknown_reference'], 404);
        }

        return response()->json(['status' => 'ok']);
    }
}
