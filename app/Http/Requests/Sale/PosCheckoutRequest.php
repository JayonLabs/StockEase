<?php

namespace App\Http\Requests\Sale;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCheckoutRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cash, PaymentMethod::Qris])],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'paid' => ['required_if:payment_method,cash', 'numeric', 'min:0', 'max:999999999999999'],
            'order_id' => ['nullable', 'string'],
        ];
    }
}
