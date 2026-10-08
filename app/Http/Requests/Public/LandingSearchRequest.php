<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class LandingSearchRequest extends FormRequest
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
            'check_in' => ['nullable', 'date', 'after_or_equal:today', 'required_with:check_out'],
            'check_out' => ['nullable', 'date', 'after:check_in', 'required_with:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.',
            'check_out.after' => 'Tanggal check-out harus setelah check-in.',
            'check_in.required_with' => 'Isi tanggal check-in dan check-out bersamaan.',
            'check_out.required_with' => 'Isi tanggal check-in dan check-out bersamaan.',
        ];
    }

    public function hasStay(): bool
    {
        return $this->filled('check_in') && $this->filled('check_out');
    }
}
