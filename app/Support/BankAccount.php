<?php

namespace App\Support;

/**
 * The account guests transfer to. Read from config/site.php so the number is never typed into code;
 * a half-filled account counts as not set, because a guest must never see a partial number.
 */
final class BankAccount
{
    public function __construct(
        public readonly ?string $bankName,
        public readonly ?string $accountNumber,
        public readonly ?string $accountHolder,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            self::clean(config('site.bank.name')),
            self::clean(config('site.bank.account_number')),
            self::clean(config('site.bank.account_holder')),
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
