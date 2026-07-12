<?php

namespace App\Infrastructure\ProxyPay;

use App\Domain\Order\Entities\Order;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class ProxyPayExpiryCalculator
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $configRepository,
    ) {}

    public function expiresAtForOrder(Order $order): \DateTimeImmutable
    {
        $minutes = max(1, (int) ($this->configRepository
            ->findByGroupAndKey('order', 'auto_cancel_minutes')?->value ?? 30));

        return $order->createdAt->modify("+{$minutes} minutes");
    }
}
