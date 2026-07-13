<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Infrastructure\Payment\PaymentChannelPolicy;
use Illuminate\Http\JsonResponse;

class PaymentChannelsController extends Controller
{
    private const CHANNEL_LABELS = [
        'alipay_sandbox' => '支付宝',
        'wechat' => '微信支付',
        'fake' => '模拟支付',
    ];

    public function __construct(
        private readonly PaymentChannelPolicy $policy,
    ) {}

    public function index(): JsonResponse
    {
        $selfPayChannels = array_map(
            fn (string $channel) => [
                'value' => $channel,
                'label' => self::CHANNEL_LABELS[$channel] ?? $channel,
            ],
            $this->policy->selfPayChannels(),
        );

        $proxyPayChannels = array_map(
            fn (string $channel) => [
                'value' => $channel,
                'label' => self::CHANNEL_LABELS[$channel] ?? $channel,
            ],
            $this->policy->proxyPayChannels(),
        );

        return ApiResponse::success([
            'self_pay' => $selfPayChannels,
            'proxy_pay' => $proxyPayChannels,
        ]);
    }
}
