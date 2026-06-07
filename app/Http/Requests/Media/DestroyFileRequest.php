<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete_files');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! str_starts_with($value, 'uploads/') || str_contains($value, '..')) {
                        $fail('Access denied.');
                    }
                },
            ],
        ];
    }
}
