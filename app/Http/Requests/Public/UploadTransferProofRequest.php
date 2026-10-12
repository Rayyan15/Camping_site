<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The guest only sends the proof file; the amount is always computed on the server.
 * The plan field stays so a down payment can be switched back on without touching the endpoint.
 */
class UploadTransferProofRequest extends FormRequest
{
    public const PLAN_FULL = 'full';

    public const PLAN_DOWN_PAYMENT = 'down_payment';

    private const MAX_KILOBYTES = 4096;

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
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::MAX_KILOBYTES],
            'plan' => ['nullable', Rule::in([self::PLAN_FULL, self::PLAN_DOWN_PAYMENT])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proof.required' => 'Pilih file bukti transfer terlebih dahulu.',
            'proof.file' => 'Bukti transfer harus berupa file.',
            'proof.mimes' => 'Bukti transfer harus berupa gambar JPG, PNG, WebP, atau PDF.',
            'proof.max' => 'Ukuran bukti transfer maksimal 4 MB.',
            'proof.uploaded' => 'File gagal diunggah. Periksa ukurannya (maksimal 4 MB) lalu coba lagi.',
        ];
    }

    public function wantsDownPayment(): bool
    {
        return $this->validated('plan') === self::PLAN_DOWN_PAYMENT;
    }
}
