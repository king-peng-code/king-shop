<?php

namespace App\Application\Order\Support;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\Services\OrderStateMachine;
use App\Domain\Order\ValueObjects\OrderStatus;

abstract class OrderStatusTransitionHandler
{
    public function __construct(
        protected readonly OrderRepositoryInterface $repository,
        protected readonly OrderStateMachine $stateMachine,
    ) {}

    protected function transition(
        int $id,
        OrderStatus $toStatus,
        ?\DateTimeImmutable $cancelledAt = null,
        ?string $cancelReason = null,
    ): Order {
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        $newStatus = $this->stateMachine->transition($order->status, $toStatus);

        return $this->repository->save(new Order(
            id: $order->id,
            orderNo: $order->orderNo,
            userId: $order->userId,
            totalAmount: $order->totalAmount,
            status: $newStatus,
            paymentMethod: $order->paymentMethod,
            paidByUserId: $order->paidByUserId,
            paidAt: $order->paidAt,
            remark: $order->remark,
            cancelledAt: $cancelledAt ?? $order->cancelledAt,
            cancelReason: $cancelReason ?? $order->cancelReason,
            createdAt: $order->createdAt,
            items: $order->items,
            userName: $order->userName,
            userPhone: $order->userPhone,
            userDepartment: $order->userDepartment,
            paidByUserName: $order->paidByUserName,
            paidByUserPhone: $order->paidByUserPhone,
            paidByUserDepartment: $order->paidByUserDepartment,
        ));
    }
}
