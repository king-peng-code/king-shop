<?php

namespace App\Application\Order\GetMyOrder;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderAccessDeniedException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;

class GetMyOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
    ) {}

    public function handle(int $orderId, int $userId): Order
    {
        $order = $this->repository->findById($orderId);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        if ($order->userId !== $userId) {
            throw new OrderAccessDeniedException();
        }

        return $order;
    }
}
