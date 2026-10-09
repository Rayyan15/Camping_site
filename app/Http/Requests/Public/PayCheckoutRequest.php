<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The guest only picks full payment or down payment; the amount is always computed on the server.
 */
class PayCheckoutRequest extends FormRequest
{
    public const PLAN_FULL = 'full';

    public const PLAN_DOWN_PAYMENT = 'down_payment';

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
            'plan' => ['nullable', Rule::in([self::PLAN_FULL, self::PLAN_DOWN_PAYMENT])],
        ];
    }

    public function wantsDownPayment(): bool
    {
        return $this->validated('plan') === self::PLAN_DOWN_PAYMENT;
    }
}
