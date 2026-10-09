<?php

namespace App\Http\Requests\Account;

use App\Services\Account\CustomerAccountService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    private const PHONE_PATTERN = '/^62\d{8,13}$/';

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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'regex:'.self::PHONE_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'phone.regex' => 'Nomor telepon tidak valid. Contoh: 0812 3456 7890.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => CustomerAccountService::normalizePhone($this->input('phone'))]);
    }
}
