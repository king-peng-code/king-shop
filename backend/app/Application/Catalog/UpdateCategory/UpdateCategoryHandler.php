<?php

namespace App\Application\Catalog\UpdateCategory;

use App\Application\Catalog\DTO\UpdateCategoryCommand;
use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\Exceptions\CategoryNotFoundException;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Infrastructure\Cache\CatalogCategoryListCache;
use App\Infrastructure\Cache\CategoryListCache;

class UpdateCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryListCache $adminCategoryCache,
        private readonly CatalogCategoryListCache $catalogCategoryCache,
    ) {}

    public function handle(UpdateCategoryCommand $command): Category
    {
        $existing = $this->repository->findById($command->categoryId)
            ?? throw new CategoryNotFoundException;

        $category = new Category(
            id: $existing->id,
            name: $command->name,
            sort: $command->sort,
            status: $command->status,
        );

        $updated = $this->repository->save($category);
        $this->adminCategoryCache->forget();
        $this->catalogCategoryCache->invalidate();

        return $updated;
    }
}
