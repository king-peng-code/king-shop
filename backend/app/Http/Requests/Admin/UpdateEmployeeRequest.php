<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', Rule::in(['employee', 'admin', 'super_admin'])],
            'status' => ['required', 'string', Rule::in(['active', 'disabled'])],
            'reset_password' => ['sometimes', 'boolean'],
        ];
    }
}
