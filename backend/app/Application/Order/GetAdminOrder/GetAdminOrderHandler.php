<?php

namespace App\Application\Order\GetAdminOrder;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;

class GetAdminOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Order
    {
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        return $order;
    }
}
