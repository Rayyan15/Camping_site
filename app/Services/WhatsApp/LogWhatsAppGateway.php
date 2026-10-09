<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/**
 * Default driver: records that a message would have gone out, and sends nothing.
 */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(private readonly PhoneNumberNormalizer $phones) {}

    public function send(string $toE164, string $message): WhatsAppSendResult
    {
        Log::info('WhatsApp message not sent (log driver)', [
            'to' => $this->phones->mask($toE164),
            'length' => mb_strlen($message),
        ]);

        return WhatsAppSendResult::sent();
    }
}
