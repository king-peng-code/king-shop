<?php

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Exceptions\InvalidPaymentSignatureException;
use App\Domain\Payment\ValueObjects\PaymentChannel;

class PaymentChannelPolicy
{
    public function __construct(
        private readonly PaymentConfigReader $config,
    ) {}

    /**
     * @return list<string>
     */
    public function selfPayChannels(): array
    {
        $channels = [];

        if ($this->config->isAvailable(PaymentChannel::ALIPAY_SANDBOX)) {
            $channels[] = PaymentChannel::ALIPAY_SANDBOX;
        }

        if ($this->config->isAvailable(PaymentChannel::WECHAT)) {
            $channels[] = PaymentChannel::WECHAT;
        }

        if ($this->fakeAllowed()) {
            $channels[] = PaymentChannel::FAKE;
        }

        return $channels;
    }

    /**
     * @return list<string>
     */
    public function proxyPayChannels(): array
    {
        $channels = [];

        if ($this->config->isAvailable(PaymentChannel::WECHAT)) {
            $channels[] = PaymentChannel::WECHAT;
        }

        if ($this->fakeAllowed()) {
            $channels[] = PaymentChannel::FAKE;
        }

        return $channels;
    }

    /**
     * @deprecated Use instance method selfPayChannels() instead
     * @return list<string>
     */
    public static function selfPayChannelsStatic(): array
    {
        $channels = [PaymentChannel::ALIPAY_SANDBOX, PaymentChannel::WECHAT];

        if (self::fakeAllowed()) {
            $channels[] = PaymentChannel::FAKE;
        }

        return $channels;
    }

    /**
     * @deprecated Use instance method proxyPayChannels() instead
     * @return list<string>
     */
    public static function proxyPayChannelsStatic(): array
    {
        $channels = [PaymentChannel::WECHAT];

        if (self::fakeAllowed()) {
            $channels[] = PaymentChannel::FAKE;
        }

        return $channels;
    }

    public static function fakeAllowed(): bool
    {
        return app()->environment('local', 'testing');
    }

    public static function assertNotifyAllowed(Payment $payment): void
    {
        if ($payment->channel->value === PaymentChannel::FAKE && ! self::fakeAllowed()) {
            throw new InvalidPaymentSignatureException();
        }
    }
}
