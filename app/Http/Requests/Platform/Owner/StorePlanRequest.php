<?php

namespace App\Http\Requests\Platform\Owner;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Platform owner middleware already enforces access; always return true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:plans,slug', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_annual' => ['required', 'numeric', 'min:0'],
            'max_products' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_warehouses' => ['nullable', 'integer', 'min:1'],
            'max_shifts' => ['nullable', 'integer', 'min:1'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*.key' => ['required', 'string'],
            'features.*.label' => ['required', 'string'],
            'features.*.included' => ['required', 'boolean'],
            'features.*.card_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
