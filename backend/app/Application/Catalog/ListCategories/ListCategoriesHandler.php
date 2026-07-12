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

        if ($cached !== null && $this->isCacheFresh($cached)) {
            return $cached;
        }

        if ($cached !== null) {
            $this->cache->forget();
        }

        $result = $this->repository->listAll();
        $this->cache->put($result);

        return $result;
    }

    /**
     * @param array{items: \App\Domain\Catalog\Entities\Category[]} $cached
     */
    private function isCacheFresh(array $cached): bool
    {
        $ids = array_map(
            fn (\App\Domain\Catalog\Entities\Category $category) => $category->id,
            $cached['items'],
        );

        return count($ids) === $this->repository->countAll()
            && $this->repository->existsAllIds($ids);
    }
}
