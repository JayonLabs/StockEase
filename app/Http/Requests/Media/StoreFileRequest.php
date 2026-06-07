<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('upload_files');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'array'],
            'file.*' => [
                'required',
                File::types(['xlsx', 'csv', 'pdf', 'xls'])
                    ->min('1kb')
                    ->max('100mb'),
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        if ($this->hasFile('file')) {
            foreach ($this->file('file') as $index => $uploadedFile) {
                $attributes["file.$index"] = $uploadedFile->getClientOriginalName();
            }
        }

        return $attributes;
    }
}
