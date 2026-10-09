<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MidtransGateway implements PaymentGateway
{
    private const REQUEST_TIMEOUT_SECONDS = 15;

    public function createTransaction(GatewayCharge $charge): string
    {
        try {
            $response = Http::withBasicAuth($this->serverKey(), '')
                ->acceptJson()
                ->asJson()
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->post(config('services.midtrans.snap_url'), $this->transactionBody($charge));
        } catch (ConnectionException $e) {
            throw PaymentException::gatewayUnreachable($e->getMessage());
        }

        $redirectUrl = $response->json('redirect_url');

        if ($response->failed() || ! is_string($redirectUrl)) {
            throw PaymentException::gatewayUnavailable('HTTP '.$response->status());
        }

        return $redirectUrl;
    }

    /**
     * @throws PaymentException when the server key is not configured
     */
    public function isAuthentic(array $payload): bool
    {
        $signature = $payload['signature_key'] ?? null;

        if (! is_string($signature)) {
            return false;
        }

        $serverKey = $this->serverKey();

        $expected = hash('sha512',
            ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$serverKey
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
    private function transactionBody(GatewayCharge $charge): array
    {
        return array_filter([
            'transaction_details' => ['order_id' => $charge->reference, 'gross_amount' => $charge->amount],
            'item_details' => [[
                'id' => $charge->reference,
                'name' => $charge->label,
                'price' => $charge->amount,
                'quantity' => 1,
            ]],
            'customer_details' => array_filter([
                'first_name' => $charge->customer['name'] ?? null,
                'phone' => $charge->customer['phone'] ?? null,
                'email' => $charge->customer['email'] ?? null,
            ]),
            'enabled_payments' => $charge->enabledPayments,
            'expiry' => ['unit' => 'minutes', 'duration' => config('booking.hold_minutes')],
            'callbacks' => ['finish' => $charge->finishUrl],
        ]);
    }

    /**
     * An empty key would let anyone compute a valid signature, so it is never usable.
     *
     * @throws PaymentException
     */
    private function serverKey(): string
    {
        $key = (string) config('services.midtrans.server_key');

        if ($key === '') {
            throw PaymentException::gatewayNotConfigured();
        }

        return $key;
    }
}
