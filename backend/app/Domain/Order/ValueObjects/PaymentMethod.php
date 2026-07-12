<?php

namespace App\Domain\Order\ValueObjects;

final class PaymentMethod
{
    public const SELF = 'self';

    public const PROXY = 'proxy';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        $allowed = [
            self::SELF,
            self::PROXY,
        ];

        if (! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid payment method: {$value}");
        }

        return new self($value);
    }
}
