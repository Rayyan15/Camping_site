<?php

namespace App\Contracts;

use App\Services\WhatsApp\WhatsAppSendResult;

interface WhatsAppGateway
{
    /**
     * Sends one text message. Provider errors come back as a failed result, never as an exception.
     *
     * @param  string  $toE164  Recipient as digits only, country code first (62812...)
     */
    public function send(string $toE164, string $message): WhatsAppSendResult;
}
