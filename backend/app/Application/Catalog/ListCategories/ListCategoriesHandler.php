<?php

namespace App\Application\Catalog\ListCategories;

use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Infrastructure\Cache\CategoryListCache;

class ListCategoriesHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryListCache $cache,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Category[]}
     */
    public function handle(): array
    {
        $cached = $this->cache->get();

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->repository->listAll();
        $this->cache->put($result);

        return $result;
    }
}
