<?php

namespace App\Http\Requests\Public;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Two read-only questions about one tent type: which nights of a month are free (`bulan`),
 * or what a stay costs (`check_in` and `check_out`).
 */
class AvailabilityCalendarRequest extends FormRequest
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
            'bulan' => ['required_without:check_in', 'nullable', 'date_format:Y-m'],
            'check_in' => ['required_without:bulan', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required_with:check_in', 'nullable', 'date_format:Y-m-d', 'after:check_in'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateMonthWindow($validator),
            fn (Validator $validator) => $this->validateStayLimits($validator),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bulan.date_format' => 'Format bulan harus TAHUN-BULAN, misalnya 2026-12.',
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.',
            'check_out.after' => 'Tanggal check-out harus setelah check-in.',
        ];
    }

    private function validateMonthWindow(Validator $validator): void
    {
        if ($validator->errors()->has('bulan') || ! $this->filled('bulan') || $this->filled('check_in')) {
            return;
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $this->input('bulan'));
        $earliest = CarbonImmutable::today()->startOfMonth();
        $latest = CarbonImmutable::today()->addDays((int) config('booking.max_advance_days'))->startOfMonth();

        if ($month->lt($earliest) || $month->gt($latest)) {
            $validator->errors()->add('bulan', 'Bulan di luar jangkauan pemesanan.');
        }
    }
}
