<?php

namespace App\Application\Catalog\DeleteCategory;

use App\Domain\Catalog\Exceptions\CategoryHasProductsException;
use App\Domain\Catalog\Exceptions\CategoryNotFoundException;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Infrastructure\Cache\CatalogCategoryListCache;
use App\Infrastructure\Cache\CategoryListCache;

class DeleteCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryListCache $adminCategoryCache,
        private readonly CatalogCategoryListCache $catalogCategoryCache,
    ) {}

    public function handle(int $id): void
    {
        $this->repository->findById($id)
            ?? throw new CategoryNotFoundException;

        if ($this->repository->countProducts($id) > 0) {
            throw new CategoryHasProductsException;
        }

        $this->repository->delete($id);
        $this->adminCategoryCache->forget();
        $this->catalogCategoryCache->invalidate();
    }
}
