<?php

namespace App\Application\Order\CompleteOrder;

use App\Application\Order\Support\OrderStatusTransitionHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\ValueObjects\OrderStatus;

class CompleteOrderHandler extends OrderStatusTransitionHandler
{
    public function handle(int $id): Order
    {
        return $this->transition($id, OrderStatus::fromString(OrderStatus::COMPLETED));
    }
}
