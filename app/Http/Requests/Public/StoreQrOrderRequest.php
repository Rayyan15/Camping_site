<?php

namespace App\Http\Requests\Public;

use App\Enums\QrPaymentChoice;
use App\Services\DiningSpotService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQrOrderRequest extends FormRequest
{
    public const MAX_LINES = 30;

    public const MAX_QTY = 20;

    public const MAX_NOTE_LENGTH = 140;

    public function authorize(): bool
    {
        // An unknown or regenerated token must look like any other missing page.
        abort_if(app(DiningSpotService::class)->findByToken((string) $this->route('token')) === null, 404);

        return true;
    }

    /**
     * The form posts one row per menu item; keep only the rows the guest actually ordered.
     */
    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('items', []))
            ->filter(fn ($row, $menuItemId) => is_array($row) && (int) ($row['qty'] ?? 0) > 0)
            ->map(fn (array $row, $menuItemId) => [
                'menu_item_id' => $menuItemId,
                'qty' => $row['qty'],
                'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
            ])
            ->values()
            ->all();

        $this->merge(['items' => $rows]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'string', 'regex:/^[0-9+\s-]{8,20}$/'],
            'payment_choice' => ['required', Rule::in($this->allowedPaymentChoices())],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'items.*.menu_item_id' => ['required', 'integer', 'distinct', Rule::exists('menu_items', 'id')->where('is_available', true)],
            'items.*.qty' => ['required', 'integer', 'between:1,'.self::MAX_QTY],
            'items.*.notes' => ['nullable', 'string', 'max:'.self::MAX_NOTE_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama pemesan wajib diisi.',
            'customer_phone.regex' => 'Nomor telepon tidak valid.',
            'payment_choice.required' => 'Pilih cara pembayaran.',
            'payment_choice.in' => 'Cara pembayaran ini tidak tersedia untuk lokasi Anda.',
            'items.required' => 'Pilih minimal satu menu.',
            'items.min' => 'Pilih minimal satu menu.',
            'items.max' => 'Terlalu banyak jenis menu dalam satu pesanan.',
            'items.*.menu_item_id.exists' => 'Ada menu yang sudah tidak tersedia. Muat ulang halaman.',
            'items.*.qty.between' => 'Jumlah per menu harus 1 sampai '.self::MAX_QTY.'.',
            'items.*.notes.max' => 'Catatan maksimal '.self::MAX_NOTE_LENGTH.' karakter.',
        ];
    }

    public function paymentChoice(): QrPaymentChoice
    {
        return QrPaymentChoice::from($this->validated('payment_choice'));
    }

    /**
     * @return array<int, string>
     */
    private function allowedPaymentChoices(): array
    {
        $spots = app(DiningSpotService::class);
        $spot = $spots->findByToken((string) $this->route('token'));

        return $spot === null ? [] : array_map(fn (QrPaymentChoice $choice) => $choice->value, $spots->paymentChoicesFor($spot));
    }
}
