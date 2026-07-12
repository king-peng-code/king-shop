<?php

namespace App\Application\Catalog\ListProducts;

use App\Application\Catalog\DTO\ProductListQuery;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;

class ListProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Product[], total: int}
     */
    public function handle(ProductListQuery $query): array
    {
        return $this->repository->searchAdmin(
            $query->categoryId,
            $query->status,
            $query->keyword,
            $query->page,
            $query->perPage,
        );
    }
}
