<?php

namespace App\Application\Order\ListAdminOrders;

use App\Application\Order\DTO\AdminOrderListQuery;
use App\Domain\Order\Repositories\OrderRepositoryInterface;

class ListAdminOrdersHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\Order\Entities\Order[], meta: array{total: int, page: int, per_page: int}}
     */
    public function handle(AdminOrderListQuery $query): array
    {
        $result = $this->repository->searchAdmin($query);

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
