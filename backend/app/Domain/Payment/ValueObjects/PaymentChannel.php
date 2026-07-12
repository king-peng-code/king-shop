<?php

namespace App\Domain\Payment\ValueObjects;

final class PaymentChannel
{
    public const ALIPAY_SANDBOX = 'alipay_sandbox';

    public const WECHAT = 'wechat';

    public const FAKE = 'fake';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! in_array($value, [self::ALIPAY_SANDBOX, self::WECHAT, self::FAKE], true)) {
            throw new \InvalidArgumentException("Invalid payment channel: {$value}");
        }

        return new self($value);
    }
}
