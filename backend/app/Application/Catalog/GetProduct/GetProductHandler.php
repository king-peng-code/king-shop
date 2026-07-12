<?php

namespace App\Application\Catalog\GetProduct;

use App\Domain\Catalog\Entities\Product;
use App\Domain\Catalog\Exceptions\ProductNotFoundException;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;

class GetProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Product
    {
        return $this->repository->findById($id)
            ?? throw new ProductNotFoundException;
    }
}
