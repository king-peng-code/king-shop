<?php

namespace App\Application\Order\CancelMyOrder;

use App\Application\Order\CancelOrder\CancelOrderHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderAccessDeniedException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;

class CancelMyOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly CancelOrderHandler $cancelOrderHandler,
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

        return $this->cancelOrderHandler->handle($orderId);
    }
}
