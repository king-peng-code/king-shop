<?php

namespace App\Domain\Order\ValueObjects;

final class OrderStatus
{
    public const PENDING_PAYMENT = 'pending_payment';

    public const PAID = 'paid';

    public const PREPARING = 'preparing';

    public const READY = 'ready';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        $allowed = [
            self::PENDING_PAYMENT,
            self::PAID,
            self::PREPARING,
            self::READY,
            self::COMPLETED,
            self::CANCELLED,
        ];

        if (! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid status: {$value}");
        }

        return new self($value);
    }
}
