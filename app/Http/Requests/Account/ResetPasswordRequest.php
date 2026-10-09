<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', 'min:'.config('auth.customer_password_min'), 'max:128', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Tautan atur ulang tidak lengkap. Minta tautan baru.',
            'email.required' => 'Isi email akun Anda.',
            'email.email' => 'Format email belum benar.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal '.config('auth.customer_password_min').' karakter.',
            'password.max' => 'Password terlalu panjang.',
            'password.confirmed' => 'Ulangi password dengan isi yang sama.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }
}
