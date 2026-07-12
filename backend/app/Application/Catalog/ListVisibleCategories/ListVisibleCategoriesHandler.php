<?php

namespace App\Application\Catalog\ListVisibleCategories;

use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Infrastructure\Cache\CatalogCategoryListCache;

class ListVisibleCategoriesHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
        private readonly CatalogCategoryListCache $cache,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Category[]}
     */
    public function handle(): array
    {
        return $this->cache->getOrSet(
            fn (): array => $this->repository->listActive(),
        );
    }
}
