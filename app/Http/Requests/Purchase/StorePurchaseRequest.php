<?php

namespace App\Http\Requests\Purchase;

use App\Enums\Role;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePurchaseRequest extends FormRequest
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
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'product_items' => ['required', 'array', 'min:1'],
            'product_items.*.product_id' => ['required', 'exists:products,id'],
            'product_items.*.qty' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'product_items.*.price' => ['required', 'numeric', 'min:1', 'max:999999999999999'],
            'product_items.*.selling_price' => ['required', 'numeric', 'min:1', 'max:999999999999999'],
            'product_items.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('date')) {

            $translatedDate = strtr($this->date, [
                'Januari' => 'January',
                'Februari' => 'February',
                'Maret' => 'March',
                'April' => 'April',
                'Mei' => 'May',
                'Juni' => 'June',
                'Juli' => 'July',
                'Agustus' => 'August',
                'September' => 'September',
                'Oktober' => 'October',
                'November' => 'November',
                'Desember' => 'December',
            ]);

            $this->merge([
                'date' => Carbon::parse($translatedDate),
            ]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_items.*.product_id.required' => 'A product is required',
            'product_items.*.qty.required' => 'A quantity is required',
            'product_items.*.price.required' => 'A price is required',
            'product_items.*.price.min' => 'The price field must be at least 1.',
        ];
    }
}
