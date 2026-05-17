<?php

namespace App\Http\Requests\Product;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole([Role::SuperAdmin->value, Role::Admin->value, Role::Warehouse->value]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255',
            'barcode' => 'required|string|max:255',
            'unit_id' => 'required|exists:units,id',
            'stock' => 'required|numeric|min:0|max:999999999999999',
            'purchase_price' => 'required|numeric|min:0|max:999999999999999',
            'selling_price' => 'required|numeric|min:0|max:999999999999999',
            'alert_stock' => 'required|numeric|min:0|max:999999999999999',
            'expiry_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ];
    }
}
