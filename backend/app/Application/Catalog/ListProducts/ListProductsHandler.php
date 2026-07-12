<?php

namespace App\Application\Catalog\ListProducts;

use App\Application\Catalog\DTO\ProductListQuery;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Cache\ProductListCache;

class ListProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly ProductListCache $cache,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Product[], total: int}
     */
    public function handle(ProductListQuery $query): array
    {
        if ($query->keyword !== '') {
            return $this->repository->searchAdmin(
                $query->categoryId,
                $query->status,
                $query->keyword,
                $query->page,
                $query->perPage,
            );
        }

        return $this->cache->getOrSet(
            type: 'admin',
            categoryId: $query->categoryId,
            status: $query->status,
            page: $query->page,
            perPage: $query->perPage,
            fallback: fn (): array => $this->repository->searchAdmin(
                $query->categoryId,
                $query->status,
                '',
                $query->page,
                $query->perPage,
            ),
        );
    }
}
