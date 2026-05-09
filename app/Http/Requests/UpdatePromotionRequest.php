<?php

namespace App\Http\Requests;

use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'discount_value' => [
                Rule::when(
                    in_array($this->input('type'), [PromotionType::Percentage->value, PromotionType::Nominal->value]),
                    ['required', 'numeric', 'min:0'],
                    ['nullable', 'numeric', 'min:0'],
                ),
                Rule::when(
                    $this->input('type') === PromotionType::Percentage->value,
                    ['max:100'],
                ),
            ],
            'buy_qty' => [
                Rule::when(
                    $this->input('type') === PromotionType::Bogo->value,
                    ['required', 'integer', 'min:1'],
                    ['nullable', 'integer', 'min:1'],
                ),
            ],
            'get_qty' => [
                Rule::when(
                    $this->input('type') === PromotionType::Bogo->value,
                    ['required', 'integer', 'min:1'],
                    ['nullable', 'integer', 'min:1'],
                ),
            ],
            'category_id' => ['nullable', 'exists:categories,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ];
    }
}
