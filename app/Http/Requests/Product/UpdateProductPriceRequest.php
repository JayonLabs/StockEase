<?php

namespace App\Http\Requests\Product;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProductPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && in_array(Auth::user()->role, [Role::Admin->value, Role::Warehouse->value]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_price' => 'required|numeric|min:0|max:999999999999999',
            'selling_price' => 'required|numeric|min:0|max:999999999999999',
            'reason' => 'required|string|max:255',
        ];
    }
}
