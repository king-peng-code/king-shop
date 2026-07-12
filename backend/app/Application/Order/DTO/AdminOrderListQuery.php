<?php

namespace App\Application\Order\DTO;

final class AdminOrderListQuery
{
    public function __construct(
        public readonly ?string $status,
        public readonly ?int $userId,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly string $keyword,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?int $paidByExternalUserId = null,
    ) {}
}
