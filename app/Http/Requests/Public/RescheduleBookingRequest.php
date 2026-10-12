<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    use ValidatesStayLimits;

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
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateStayLimits($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_in.required' => 'Isi tanggal check-in baru.',
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.',
            'check_out.required' => 'Isi tanggal check-out baru.',
            'check_out.after' => 'Tanggal check-out harus setelah check-in.',
        ];
    }
}
