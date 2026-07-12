<?php

namespace App\Application\Order\DTO;

use App\Domain\Order\ValueObjects\PaymentMethod;

final class CreateOrderItemCommand
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
    ) {}
}

final class CreateOrderCommand
{
    /**
     * @param  CreateOrderItemCommand[]  $items
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $items,
        public readonly PaymentMethod $paymentMethod,
        public readonly ?string $remark,
    ) {}
}
