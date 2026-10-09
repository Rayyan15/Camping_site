<?php

namespace App\Http\Requests\Account;

use App\Services\Account\CustomerAccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    private const PHONE_PATTERN = '/^62\d{8,13}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Email uniqueness is deliberately not validated here: the answer would reveal who has an account.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'regex:'.self::PHONE_PATTERN],
            'password' => ['required', 'string', 'min:'.config('auth.customer_password_min'), 'max:128', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum benar.',
            'phone.regex' => 'Nomor telepon tidak valid. Contoh: 0812 3456 7890.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal '.config('auth.customer_password_min').' karakter.',
            'password.max' => 'Password terlalu panjang.',
            'password.confirmed' => 'Ulangi password dengan isi yang sama.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => CustomerAccountService::normalizePhone($this->input('phone')),
        ]);
    }
}
