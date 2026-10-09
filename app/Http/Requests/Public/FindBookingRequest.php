<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class FindBookingRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32'],
            'proof' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Isi kode booking.',
            'proof.required' => 'Isi 4 digit terakhir nomor telepon atau email booking.',
        ];
    }
}
