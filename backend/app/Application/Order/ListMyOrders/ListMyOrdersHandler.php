<?php

namespace App\Application\Order\ListMyOrders;

use App\Application\Order\DTO\UserOrderListQuery;
use App\Domain\Order\Repositories\OrderRepositoryInterface;

class ListMyOrdersHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\Order\Entities\Order[], meta: array{total: int, page: int, per_page: int}}
     */
    public function handle(UserOrderListQuery $query): array
    {
        $result = $this->repository->searchUser($query);

        return [
            'items' => $result['items'],
            'meta' => [
                'total' => $result['total'],
                'page' => $query->page,
                'per_page' => $query->perPage,
            ],
        ];
    }
}
