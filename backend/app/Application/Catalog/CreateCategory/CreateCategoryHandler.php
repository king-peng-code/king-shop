<?php

namespace App\Application\Catalog\CreateCategory;

use App\Application\Catalog\DTO\CreateCategoryCommand;
use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Infrastructure\Cache\CategoryListCache;

class CreateCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryListCache $cache,
    ) {}

    public function handle(CreateCategoryCommand $command): Category
    {
        $category = new Category(
            id: null,
            name: $command->name,
            sort: $command->sort,
            status: $command->status,
        );

        $created = $this->repository->save($category);
        $this->cache->forget();

        return $created;
    }
}
