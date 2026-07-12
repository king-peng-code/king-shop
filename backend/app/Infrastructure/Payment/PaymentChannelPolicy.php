<?php

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Exceptions\InvalidPaymentSignatureException;
use App\Domain\Payment\ValueObjects\PaymentChannel;

class PaymentChannelPolicy
{
    /**
     * @return list<string>
     */
    public static function selfPayChannels(): array
    {
        $channels = [PaymentChannel::ALIPAY_SANDBOX, PaymentChannel::WECHAT];

        if (self::fakeAllowed()) {
            $channels[] = PaymentChannel::FAKE;
        }

        return $channels;
    }

    /**
     * @return list<string>
     */
    public static function proxyPayChannels(): array
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
