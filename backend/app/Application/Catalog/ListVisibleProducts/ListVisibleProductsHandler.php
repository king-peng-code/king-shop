<?php

namespace App\Application\Catalog\ListVisibleProducts;

use App\Domain\Catalog\Repositories\ProductRepositoryInterface;

class ListVisibleProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Product[], total: int}
     */
    public function handle(?int $categoryId, int $page, int $perPage): array
    {
        return $this->repository->searchVisible($categoryId, $page, $perPage);
    }
}
