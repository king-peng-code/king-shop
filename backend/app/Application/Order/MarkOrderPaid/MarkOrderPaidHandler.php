<?php

namespace App\Application\Order\MarkOrderPaid;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\Services\OrderStateMachine;
use App\Domain\Order\ValueObjects\OrderStatus;

class MarkOrderPaidHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function handle(int $orderId, ?int $paidByExternalUserId = null, ?\DateTimeImmutable $paidAt = null): Order
    {
        $order = $this->repository->findById($orderId);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        if ($order->status->value === OrderStatus::PAID) {
            return $order;
        }

        $newStatus = $this->stateMachine->transition(
            $order->status,
            OrderStatus::fromString(OrderStatus::PAID),
        );

        return $this->repository->save(new Order(
            id: $order->id,
            orderNo: $order->orderNo,
            userId: $order->userId,
            totalAmount: $order->totalAmount,
            status: $newStatus,
            paymentMethod: $order->paymentMethod,
            paidByExternalUserId: $paidByExternalUserId ?? $order->paidByExternalUserId,
            paidAt: $paidAt ?? new \DateTimeImmutable,
            remark: $order->remark,
            cancelledAt: $order->cancelledAt,
            cancelReason: $order->cancelReason,
            createdAt: $order->createdAt,
            items: $order->items,
            userName: $order->userName,
            userPhone: $order->userPhone,
            paidByPayerName: $order->paidByPayerName,
            paidByPayerPhone: $order->paidByPayerPhone,
            paidByPayerProvider: $order->paidByPayerProvider,
        ));
    }
}
