<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmployeeRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^1\d{10}$/', 'unique:users,phone'],
            'employee_no' => ['nullable', 'string', 'max:50', 'unique:users,employee_no'],
            'department' => ['nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'string', Rule::in(['employee', 'admin', 'super_admin'])],
        ];
    }
}
