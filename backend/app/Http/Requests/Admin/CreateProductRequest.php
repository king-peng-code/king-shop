<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:1'],
            'upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in(['on_sale', 'off_sale'])],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
