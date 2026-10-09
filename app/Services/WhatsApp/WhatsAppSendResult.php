<?php

namespace App\Services\WhatsApp;

final readonly class WhatsAppSendResult
{
    private function __construct(
        public bool $successful,
        public ?string $providerMessageId,
        public ?string $reason,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId, null);
    }

    public static function failed(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
