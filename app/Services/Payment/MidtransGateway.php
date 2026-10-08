<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MidtransGateway implements PaymentGateway
{
    private const REQUEST_TIMEOUT_SECONDS = 15;

    public function createTransaction(Booking $booking, string $orderId, int $amount): string
    {
        $booking->loadMissing('customer');

        try {
            $response = Http::withBasicAuth($this->serverKey(), '')
                ->acceptJson()
                ->asJson()
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->post(config('services.midtrans.snap_url'), $this->transactionBody($booking, $orderId, $amount));
        } catch (ConnectionException $e) {
            throw PaymentException::gatewayUnavailable($e->getMessage());
        }

        $redirectUrl = $response->json('redirect_url');

        if ($response->failed() || ! is_string($redirectUrl)) {
            throw PaymentException::gatewayUnavailable('HTTP '.$response->status());
        }

        return $redirectUrl;
    }

    public function isAuthentic(array $payload): bool
    {
        $signature = $payload['signature_key'] ?? null;

        if (! is_string($signature)) {
            return false;
        }

        $expected = hash('sha512',
            ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$this->serverKey()
        );

        return hash_equals($expected, $signature);
    }

    public function statusFrom(array $payload): PaymentStatus
    {
        return PaymentStatus::fromGatewayStatus(
            (string) ($payload['transaction_status'] ?? ''),
            $payload['fraud_status'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionBody(Booking $booking, string $orderId, int $amount): array
    {
        return [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'item_details' => [[
                'id' => $booking->code,
                'name' => 'Booking '.$booking->code,
                'price' => $amount,
                'quantity' => 1,
            ]],
            'customer_details' => array_filter([
                'first_name' => $booking->customer?->name,
                'phone' => $booking->customer?->phone,
                'email' => $booking->customer?->email,
            ]),
            'expiry' => ['unit' => 'minutes', 'duration' => config('booking.hold_minutes')],
            'callbacks' => ['finish' => route('booking.status', $booking->code)],
        ];
    }

    private function serverKey(): string
    {
        return (string) config('services.midtrans.server_key');
    }
}
