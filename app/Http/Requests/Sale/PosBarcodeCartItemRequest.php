<?php

namespace App\Http\Requests\Sale;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PosBarcodeCartItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
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
