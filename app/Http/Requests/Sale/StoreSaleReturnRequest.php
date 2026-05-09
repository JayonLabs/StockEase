<?php

namespace App\Http\Requests\Sale;

use App\Enums\SaleReturnType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleReturnRequest extends FormRequest
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
            'return_type' => ['required', Rule::enum(SaleReturnType::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'return_type.required' => 'Tipe retur harus dipilih.',
            'return_type.in' => 'Tipe retur tidak valid.',
            'items.required' => 'Minimal satu produk harus dipilih untuk retur.',
            'items.min' => 'Minimal satu produk harus dipilih untuk retur.',
            'items.*.sale_item_id.required' => 'Item penjualan harus dipilih.',
            'items.*.sale_item_id.exists' => 'Item penjualan tidak valid.',
            'items.*.qty.required' => 'Jumlah retur harus diisi.',
            'items.*.qty.min' => 'Jumlah retur minimal 1.',
        ];
    }
}
