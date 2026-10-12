<?php

namespace App\Support;

use App\Enums\SettingKey;
use App\Services\SettingRepository;

/**
 * The account guests transfer to. The owner fills it in Pengaturan; config/site.php (SITE_BANK_*) is the
 * fallback for a field left empty there. The number is never typed into code;
 * a half-filled account counts as not set, because a guest must never see a partial number.
 */
final class BankAccount
{
    public function __construct(
        public readonly ?string $bankName,
        public readonly ?string $accountNumber,
        public readonly ?string $accountHolder,
    ) {}

    public static function current(): self
    {
        $settings = app(SettingRepository::class);

        return new self(
            self::clean($settings->get(SettingKey::BankName)) ?? self::clean(config('site.bank.name')),
            self::clean($settings->get(SettingKey::BankAccountNumber)) ?? self::clean(config('site.bank.account_number')),
            self::clean($settings->get(SettingKey::BankAccountHolder)) ?? self::clean(config('site.bank.account_holder')),
        );
    }

    public function isComplete(): bool
    {
        return $this->bankName !== null && $this->accountNumber !== null && $this->accountHolder !== null;
    }

    private static function clean(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
