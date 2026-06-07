<?php

namespace App\Http\Requests\Sale;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([Role::SuperAdmin->value, Role::Admin->value, Role::Cashier->value]) ?? false;
    }

    /**
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
