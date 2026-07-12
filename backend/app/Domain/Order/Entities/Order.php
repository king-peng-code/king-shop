<?php

namespace App\Domain\Order\Entities;

use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;

final class Order
{
    /**
     * @param  OrderItem[]  $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $orderNo,
        public readonly int $userId,
        public readonly int $totalAmount,
        public readonly OrderStatus $status,
        public readonly PaymentMethod $paymentMethod,
        public readonly ?int $paidByUserId,
        public readonly ?\DateTimeImmutable $paidAt,
        public readonly ?string $remark,
        public readonly ?\DateTimeImmutable $cancelledAt,
        public readonly ?string $cancelReason,
        public readonly ?\DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt,
        public readonly array $items = [],
        public readonly ?string $userName = null,
        public readonly ?string $userPhone = null,
        public readonly ?string $userDepartment = null,
        public readonly ?string $paidByUserName = null,
    ) {}

    public function withStatus(OrderStatus $status): self
    {
        return new self(
            id: $this->id,
            orderNo: $this->orderNo,
            userId: $this->userId,
            totalAmount: $this->totalAmount,
            status: $status,
            paymentMethod: $this->paymentMethod,
            paidByUserId: $this->paidByUserId,
            paidAt: $this->paidAt,
            remark: $this->remark,
            cancelledAt: $this->cancelledAt,
            cancelReason: $this->cancelReason,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            items: $this->items,
            userName: $this->userName,
            userPhone: $this->userPhone,
            userDepartment: $this->userDepartment,
            paidByUserName: $this->paidByUserName,
        );
    }

    public function withCancelled(\DateTimeImmutable $cancelledAt, ?string $cancelReason): self
    {
        return new self(
            id: $this->id,
            orderNo: $this->orderNo,
            userId: $this->userId,
            totalAmount: $this->totalAmount,
            status: $this->status,
            paymentMethod: $this->paymentMethod,
            paidByUserId: $this->paidByUserId,
            paidAt: $this->paidAt,
            remark: $this->remark,
            cancelledAt: $cancelledAt,
            cancelReason: $cancelReason,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            items: $this->items,
            userName: $this->userName,
            userPhone: $this->userPhone,
            userDepartment: $this->userDepartment,
            paidByUserName: $this->paidByUserName,
        );
    }
}
