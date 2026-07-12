<?php

namespace App\Application\Order\CancelOrder;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\Services\OrderStateMachine;
use App\Domain\Order\ValueObjects\OrderStatus;

class CancelOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function handle(int $id, ?string $cancelReason = null): Order
    {
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        $newStatus = $this->stateMachine->transition(
            $order->status,
            OrderStatus::fromString(OrderStatus::CANCELLED),
        );

        $updated = $order
            ->withStatus($newStatus)
            ->withCancelled(new \DateTimeImmutable(), $cancelReason);

        return $this->repository->save($updated);
    }
}
