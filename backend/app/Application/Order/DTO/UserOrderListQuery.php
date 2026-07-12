<?php

namespace App\Application\Order\DTO;

final class UserOrderListQuery
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $status,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}
