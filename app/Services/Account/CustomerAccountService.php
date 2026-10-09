<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class CustomerAccountService
{
    /**
     * Staff accounts live in the admin panel only. An account counts as a customer account
     * when it is active and holds none of the panel roles.
     */
    public function isCustomerAccount(User $user): bool
    {
        return $user->is_active && ! $user->hasAnyRole(User::PANEL_ROLES);
    }

    /**
     * Never reveals whether the email is taken: a new email gets an account and a verification
     * mail, an unverified customer account gets a fresh mail, anything else is left untouched.
     *
     * @param  array{name: string, email: string, password: string, phone?: string|null}  $data
     */
    public function register(array $data): void
    {
        $existing = User::where('email', $data['email'])->first();

        if ($existing === null) {
            $this->createAccount($data);

            return;
        }

        if ($this->isCustomerAccount($existing) && ! $existing->hasVerifiedEmail()) {
            $existing->sendEmailVerificationNotification();
        }
    }

    /**
     * Digits only with the Indonesian country code, the same shape the booking form stores.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }

    public static function localPhone(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        return str_starts_with($phone, '62') ? '0'.substr($phone, 2) : $phone;
    }

    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null}  $data
     */
    private function createAccount(array $data): void
    {
        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            Log::info('Customer sign-up lost a race on the email address.', ['reason' => $exception->getMessage()]);

            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
