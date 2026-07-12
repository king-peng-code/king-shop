<?php

namespace App\Domain\Payment\ValueObjects;

final class PaymentStatus
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! in_array($value, [self::PENDING, self::SUCCESS, self::FAILED], true)) {
            throw new \InvalidArgumentException("Invalid payment status: {$value}");
        }

        return new self($value);
    }

    public function isSuccess(): bool
    {
        return $this->value === self::SUCCESS;
    }
}
