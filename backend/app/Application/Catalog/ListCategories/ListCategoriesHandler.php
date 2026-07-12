<?php

namespace App\Application\Catalog\ListCategories;

use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;

class ListCategoriesHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Category[]}
     */
    public function handle(): array
    {
        return $this->repository->listAll();
    }
}
