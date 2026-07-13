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
        $policy = app(PaymentChannelPolicy::class);

        return [
            'channel' => ['nullable', Rule::in($policy->proxyPayChannels())],
            'provider' => ['required', Rule::in(['wechat', 'alipay', 'fake'])],
            'external_id' => ['nullable', 'string', 'max:128'],
            'payer_name' => ['nullable', 'string', 'max:100'],
            'openid' => ['nullable', 'string', 'max:128'],
        ];
    }
}
