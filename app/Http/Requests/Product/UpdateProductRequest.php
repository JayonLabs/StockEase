<?php

namespace App\Http\Requests\Product;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProductRequest extends FormRequest
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
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255',
            'barcode' => 'required|string|max:255',
            'unit_id' => 'required|exists:units,id',
            'alert_stock' => 'required|numeric|min:0|max:999999999999999',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ];
    }
}
