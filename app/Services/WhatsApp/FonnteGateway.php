<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FonnteGateway implements WhatsAppGateway
{
    private const REQUEST_TIMEOUT_SECONDS = 15;

    private const RETRY_ATTEMPTS = 2;

    private const RETRY_DELAY_MS = 500;

    /**
     * @throws WhatsAppConfigurationException when the token is not configured
     */
    public function send(string $toE164, string $message): WhatsAppSendResult
    {
        $token = $this->token();

        try {
            $response = Http::withHeaders(['Authorization' => $token])
                ->acceptJson()
                ->asForm()
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, throw: false)
                ->post(config('whatsapp.fonnte.url'), ['target' => $toE164, 'message' => $message]);
        } catch (ConnectionException) {
            return WhatsAppSendResult::failed('Penyedia WhatsApp tidak dapat dihubungi.');
        }

        if ($response->failed() || $response->json('status') !== true) {
            $reason = $response->json('reason');

            return WhatsAppSendResult::failed(is_string($reason) && $reason !== ''
                ? $reason
                : 'Penyedia WhatsApp menolak pesan (HTTP '.$response->status().').');
        }

        $id = $response->json('id.0') ?? $response->json('id');

        return WhatsAppSendResult::sent(is_scalar($id) ? (string) $id : null);
    }

    /**
     * @throws WhatsAppConfigurationException
     */
    private function token(): string
    {
        $token = (string) config('whatsapp.fonnte.token');

        if ($token === '') {
            throw WhatsAppConfigurationException::missingFonnteToken();
        }

        return $token;
    }
}
