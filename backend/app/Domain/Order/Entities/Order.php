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
        public readonly \DateTimeImmutable $createdAt,
        public readonly array $items = [],
        public readonly ?string $userName = null,
        public readonly ?string $userPhone = null,
        public readonly ?string $userDepartment = null,
        public readonly ?string $paidByUserName = null,
        public readonly ?string $paidByUserPhone = null,
        public readonly ?string $paidByUserDepartment = null,
    ) {}
}
