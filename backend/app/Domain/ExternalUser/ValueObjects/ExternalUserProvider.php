<?php

namespace App\Domain\ExternalUser\ValueObjects;

final class ExternalUserProvider
{
    public const WECHAT = 'wechat';

    public const ALIPAY = 'alipay';

    public const FAKE = 'fake';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! in_array($value, [self::WECHAT, self::ALIPAY, self::FAKE], true)) {
            throw new \InvalidArgumentException("Invalid external user provider: {$value}");
        }

        return new self($value);
    }
}
