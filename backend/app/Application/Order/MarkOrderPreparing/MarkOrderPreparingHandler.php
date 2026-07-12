<?php

namespace App\Application\Order\MarkOrderPreparing;

use App\Application\Order\Support\OrderStatusTransitionHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\ValueObjects\OrderStatus;

class MarkOrderPreparingHandler extends OrderStatusTransitionHandler
{
    public function handle(int $id): Order
    {
        return $this->transition($id, OrderStatus::fromString(OrderStatus::PREPARING));
    }
}
