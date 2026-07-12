<?php

namespace App\Domain\Payment\DTO;

use App\Domain\Payment\ValueObjects\PaymentStatus;

final class PaymentQueryResult
{
    private function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $tradeNo,
    ) {}

    public static function success(string $tradeNo): self
    {
        return new self(PaymentStatus::fromString(PaymentStatus::SUCCESS), $tradeNo);
    }

    public static function pending(): self
    {
        return new self(PaymentStatus::fromString(PaymentStatus::PENDING), null);
    }

    public static function failed(): self
    {
        return new self(PaymentStatus::fromString(PaymentStatus::FAILED), null);
    }
}
