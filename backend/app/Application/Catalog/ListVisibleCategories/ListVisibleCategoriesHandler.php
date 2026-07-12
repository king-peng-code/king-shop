<?php

namespace App\Application\Catalog\ListVisibleCategories;

use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;

class ListVisibleCategoriesHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Category[]}
     */
    public function handle(): array
    {
        return $this->repository->listActive();
    }
}
