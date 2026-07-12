<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class InitiateProxyPaymentRequest extends FormRequest
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
            'openid' => ['nullable', 'string', 'max:128'],
            'channel' => ['nullable', 'in:wechat,fake'],
        ];
    }
}
