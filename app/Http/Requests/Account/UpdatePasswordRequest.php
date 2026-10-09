<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:'.config('auth.customer_password_min'), 'max:128', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Isi password Anda saat ini.',
            'current_password.current_password' => 'Password saat ini tidak cocok.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal '.config('auth.customer_password_min').' karakter.',
            'password.max' => 'Password terlalu panjang.',
            'password.confirmed' => 'Ulangi password baru dengan isi yang sama.',
            'password.different' => 'Password baru harus berbeda dari yang sekarang.',
        ];
    }
}
