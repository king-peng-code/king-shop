<?php

namespace App\Http\Requests\Catalog;

use App\Infrastructure\Payment\PaymentChannelPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'channel' => ['nullable', Rule::in(PaymentChannelPolicy::proxyPayChannels())],
        ];
    }
}
