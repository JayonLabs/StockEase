<?php

namespace App\Http\Requests\Platform\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
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
     * Slug uniqueness excludes the current plan to allow re-saving the same slug.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('plans', 'slug')->ignore($this->plan->id)->whereNull('deleted_at'),
                'regex:/^[a-z0-9-]+$/',
            ],
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
