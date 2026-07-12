<?php

namespace App\Domain\Order\Repositories;

use App\Application\Order\DTO\AdminOrderListQuery;
use App\Application\Order\DTO\UserOrderListQuery;
use App\Domain\Order\Entities\Order;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    /**
     * @return array{items: Order[], total: int}
     */
    public function searchAdmin(AdminOrderListQuery $query): array;

    /**
     * @return array{items: Order[], total: int}
     */
    public function searchUser(UserOrderListQuery $query): array;

    /**
     * @return Order[]
     */
    public function findExpiredPendingPayment(int $minutes): array;

    public function save(Order $order): Order;
}
