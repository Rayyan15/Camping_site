<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\Route;
use LogicException;

/**
 * Development and test gateway. It performs no network call and trusts every notification,
 * so it refuses to run in production.
 */
class FakeGateway implements PaymentGateway
{
    public function __construct()
    {
        if (app()->isProduction()) {
            throw new LogicException('FakeGateway must not be used in production. Set PAYMENT_GATEWAY=midtrans.');
        }
    }

    public function createTransaction(Booking $booking, string $orderId, int $amount): string
    {
        // The dev page only exists on a local machine; elsewhere (tests) fall back to the status page.
        return Route::has('dev.pay.show')
            ? route('dev.pay.show', $orderId)
            : route('booking.status', $booking->code);
    }

    public function isAuthentic(array $payload): bool
    {
        return true;
    }

    public function statusFrom(array $payload): PaymentStatus
    {
        return PaymentStatus::fromGatewayStatus((string) ($payload['transaction_status'] ?? ''));
    }
}
