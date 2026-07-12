<?php

namespace App\Http\Requests\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSystemConfigsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'configs' => ['required', 'array', 'min:1'],
            'configs.*.group' => ['required', 'string', 'in:app,payment,storage,order'],
            'configs.*.key' => ['required', 'string', 'max:100'],
            'configs.*.value' => ['present', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('configs', []) as $index => $config) {
                if (! is_array($config)) {
                    continue;
                }

                $exists = SystemConfigModel::query()
                    ->where('group', $config['group'] ?? '')
                    ->where('key', $config['key'] ?? '')
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        "configs.{$index}.key",
                        'Unknown config key for the given group.',
                    );
                }
            }
        });
    }
}
