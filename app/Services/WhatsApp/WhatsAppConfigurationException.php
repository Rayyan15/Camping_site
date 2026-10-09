<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class WhatsAppConfigurationException extends RuntimeException
{
    public static function missingFonnteToken(): self
    {
        return new self('WHATSAPP_FONNTE_TOKEN belum diisi, pesan WhatsApp tidak dikirim.');
    }
}
