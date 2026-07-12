<?php

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Exceptions\UnsupportedPaymentProviderException;
use App\Domain\Payment\Services\PaymentGatewayInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Infrastructure\Payment\Gateways\AlipaySandboxGateway;
use App\Infrastructure\Payment\Gateways\FakePaymentGateway;
use App\Infrastructure\Payment\Gateways\WechatPayGateway;

class ConfigPaymentGatewayResolver implements PaymentGatewayResolverInterface
{
    public function __construct(
        private readonly PaymentConfigReader $config,
        private readonly AlipaySandboxGateway $alipayGateway,
        private readonly WechatPayGateway $wechatGateway,
        private readonly FakePaymentGateway $fakeGateway,
    ) {}

    public function resolve(?string $channel = null): PaymentGatewayInterface
    {
        $provider = $channel ?? $this->config->provider();

        return match ($provider) {
            PaymentChannel::ALIPAY_SANDBOX => $this->alipayGateway,
            PaymentChannel::WECHAT => $this->wechatGateway,
            PaymentChannel::FAKE => $this->fakeGateway,
            default => throw new UnsupportedPaymentProviderException(),
        };
    }
}
