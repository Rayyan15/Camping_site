<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public const SORTS = ['termurah', 'termahal', 'kapasitas'];

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
            'tamu' => ['nullable', 'integer', 'min:1', 'max:50'],
            'urutkan' => ['nullable', Rule::in(self::SORTS)],
            'fasilitas' => ['nullable', 'array'],
            'fasilitas.*' => ['string', 'max:100'],
        ];
    }
}
