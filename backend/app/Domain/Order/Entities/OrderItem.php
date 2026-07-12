<?php

namespace App\Domain\Order\Entities;

final class OrderItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $productId,
        public readonly string $productName,
        public readonly ?string $productImage,
        public readonly int $price,
        public readonly int $quantity,
        public readonly int $subtotal,
    ) {}
}
