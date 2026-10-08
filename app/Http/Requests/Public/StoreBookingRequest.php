<?php

namespace App\Http\Requests\Public;

use App\Models\Addon;
use App\Models\Unit;
use App\Models\UnitType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    private const PHONE_PATTERN = '/^62\d{8,13}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'regex:'.self::PHONE_PATTERN],
            'customer_email' => ['required', 'email:rfc', 'max:190'],
            'guests' => ['required', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'unit_type_id' => ['required', 'integer', 'exists:unit_types,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('booking.max_units_per_booking')],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['integer', 'min:1', 'max:'.config('booking.max_addon_quantity')],
            'preorder' => ['nullable', 'array'],
            'preorder.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('is_available', true)],
            'preorder.*.qty' => ['required', 'integer', 'min:1', 'max:'.config('booking.max_preorder_quantity')],
            'preorder.*.serve_date' => ['required', 'date', 'after_or_equal:check_in', 'before:check_out'],
            'preorder.*.serve_time' => ['required', Rule::in(config('booking.serve_times'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama lengkap wajib diisi.',
            'customer_phone.required' => 'Nomor WhatsApp wajib diisi.',
            'customer_phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.',
            'customer_email.required' => 'Email wajib diisi.',
            'customer_email.email' => 'Format email belum benar.',
            'guests.required' => 'Jumlah tamu wajib diisi.',
            'guests.min' => 'Jumlah tamu minimal 1 orang.',
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.',
            'check_out.after' => 'Tanggal check-out harus setelah check-in.',
            'unit_type_id.exists' => 'Tipe tenda tidak ditemukan.',
            'quantity.min' => 'Pilih minimal 1 unit.',
            'quantity.max' => 'Satu pemesanan maksimal '.config('booking.max_units_per_booking').' unit.',
            'addons.*.max' => 'Jumlah tambahan melebihi batas yang diizinkan.',
            'preorder.*.menu_item_id.exists' => 'Salah satu menu yang dipilih sedang tidak tersedia.',
            'preorder.*.qty.max' => 'Jumlah menu melebihi batas yang diizinkan.',
            'preorder.*.serve_date.after_or_equal' => 'Tanggal penyajian harus berada dalam masa menginap.',
            'preorder.*.serve_date.before' => 'Tanggal penyajian harus sebelum hari check-out.',
            'preorder.*.serve_time.in' => 'Jam penyajian tidak tersedia.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_phone' => $this->normalizePhone((string) $this->input('customer_phone')),
            'addons' => $this->chosenAddons(),
            'preorder' => $this->chosenPreorderItems(),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateAddonsExist($validator);
            $this->validateUnitCapacity($validator);
        }];
    }

    private function validateAddonsExist(Validator $validator): void
    {
        $requested = array_keys($this->input('addons', []));
        $known = Addon::where('is_active', true)->whereIn('id', $requested)->count();

        if ($known !== count($requested)) {
            $validator->errors()->add('addons', 'Salah satu tambahan yang dipilih tidak tersedia.');
        }
    }

    private function validateUnitCapacity(Validator $validator): void
    {
        $unitType = UnitType::findOrFail($this->integer('unit_type_id'));
        $quantity = $this->integer('quantity');

        $activeUnits = Unit::where('unit_type_id', $unitType->id)->where('status', Unit::STATUS_ACTIVE)->count();
        if ($quantity > $activeUnits) {
            $validator->errors()->add('quantity', 'Jumlah unit yang diminta melebihi unit yang tersedia.');

            return;
        }

        if ($this->integer('guests') > $unitType->capacity * $quantity + $this->extraBedCount()) {
            $validator->errors()->add('guests', "Kapasitas {$unitType->name} adalah {$unitType->capacity} orang per unit. Tambah unit atau extra bed.");
        }
    }

    private function extraBedCount(): int
    {
        $perNightIds = Addon::where('unit', Addon::UNIT_PER_NIGHT)->pluck('id')->all();

        return (int) collect($this->input('addons', []))
            ->only($perNightIds)
            ->sum();
    }

    /**
     * Digits only, with the Indonesian country code 62 in front.
     */
    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }

    /**
     * @return array<int|string, mixed>
     */
    private function chosenAddons(): array
    {
        $addons = $this->input('addons', []);

        return is_array($addons) ? array_filter($addons, fn ($qty) => (int) $qty > 0) : [];
    }

    /**
     * The form posts preorder[menu_item_id][qty|serve_date|serve_time]; rows with qty 0 are unchosen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function chosenPreorderItems(): array
    {
        $rows = $this->input('preorder', []);
        if (! is_array($rows)) {
            return [];
        }

        $chosen = [];
        foreach ($rows as $menuItemId => $row) {
            if (is_array($row) && (int) ($row['qty'] ?? 0) > 0) {
                $chosen[] = $row + ['menu_item_id' => $menuItemId];
            }
        }

        return $chosen;
    }
}
