<?php

namespace App\Application\Order\CancelExpiredOrders;

use App\Application\Order\CancelOrder\CancelOrderHandler;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class CancelExpiredOrdersHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SystemConfigRepositoryInterface $configRepository,
        private readonly CancelOrderHandler $cancelOrderHandler,
    ) {}

    public function handle(): int
    {
        $minutes = $this->resolveAutoCancelMinutes();
        $expiredOrders = $this->orderRepository->findExpiredPendingPayment($minutes);
        $cancelled = 0;

        foreach ($expiredOrders as $order) {
            if ($order->id === null) {
                continue;
            }

            $this->cancelOrderHandler->handle($order->id, '超时未支付自动取消');
            $cancelled++;
        }

        return $cancelled;
    }

    private function resolveAutoCancelMinutes(): int
    {
        $config = $this->configRepository->findByGroupAndKey('order', 'auto_cancel_minutes');
        $minutes = (int) ($config?->value ?? 30);

        return max(1, $minutes);
    }
}
