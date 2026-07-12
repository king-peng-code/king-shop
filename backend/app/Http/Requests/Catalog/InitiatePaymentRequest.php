<?php

namespace App\Http\Requests\Catalog;

use App\Infrastructure\Payment\PaymentChannelPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
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
            'channel' => ['nullable', Rule::in(PaymentChannelPolicy::selfPayChannels())],
        ];
    }
}
