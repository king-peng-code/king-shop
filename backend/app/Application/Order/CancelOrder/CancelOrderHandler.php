<?php

namespace App\Application\Order\CancelOrder;

use App\Application\Order\Support\OrderStatusTransitionHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\ValueObjects\OrderStatus;

class CancelOrderHandler extends OrderStatusTransitionHandler
{
    public function handle(int $id, ?string $cancelReason = null): Order
    {
        return $this->transition(
            $id,
            OrderStatus::fromString(OrderStatus::CANCELLED),
            cancelledAt: new \DateTimeImmutable(),
            cancelReason: $cancelReason,
        );
    }
}
