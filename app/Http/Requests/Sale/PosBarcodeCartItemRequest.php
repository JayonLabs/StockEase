<?php

namespace App\Http\Requests\Sale;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PosBarcodeCartItemRequest extends FormRequest
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
            'barcode' => ['required', 'string', 'exists:products,barcode'],
            'qty' => ['sometimes', 'required', 'numeric', 'min:1'],
        ];
    }
}
