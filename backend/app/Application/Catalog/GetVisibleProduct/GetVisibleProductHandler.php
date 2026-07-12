<?php

namespace App\Application\Catalog\GetVisibleProduct;

use App\Domain\Catalog\Entities\Product;
use App\Domain\Catalog\Exceptions\ProductNotFoundException;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;

class GetVisibleProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Product
    {
        return $this->repository->findVisibleById($id)
            ?? throw new ProductNotFoundException;
    }
}
