<?php

namespace App\Application\Catalog\ListVisibleProducts;

use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Cache\ProductListCache;

class ListVisibleProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly ProductListCache $cache,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Product[], total: int}
     */
    public function handle(?int $categoryId, int $page, int $perPage): array
    {
        return $this->cache->getOrSet(
            type: 'visible',
            categoryId: $categoryId,
            status: null,
            page: $page,
            perPage: $perPage,
            fallback: fn (): array => $this->repository->searchVisible($categoryId, $page, $perPage),
        );
    }
}
