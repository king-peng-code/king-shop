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
        $employeeId = $this->route('employee');

        return [
            'name' => ['required', 'string', 'max:100'],
            'employee_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_no')->ignore($employeeId),
            ],
            'department' => ['nullable', 'string', 'max:100'],
            'role' => ['required', 'string', Rule::in(['employee', 'admin', 'super_admin'])],
            'status' => ['required', 'string', Rule::in(['active', 'disabled'])],
            'reset_password' => ['sometimes', 'boolean'],
        ];
    }
}
