<?php

namespace App\Domain\Payment\Entities;

use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Domain\Payment\ValueObjects\PaymentStatus;

final class Payment
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $orderId,
        public readonly ?int $payerExternalUserId,
        public readonly string $outTradeNo,
        public readonly ?string $tradeNo,
        public readonly int $amount,
        public readonly PaymentChannel $channel,
        public readonly PaymentStatus $status,
        public readonly ?\DateTimeImmutable $paidAt,
        public readonly ?array $rawNotify,
    ) {}
}
