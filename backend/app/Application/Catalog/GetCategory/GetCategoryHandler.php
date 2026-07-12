<?php

namespace App\Application\Catalog\GetCategory;

use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\Exceptions\CategoryNotFoundException;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;

class GetCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Category
    {
        return $this->repository->findById($id)
            ?? throw new CategoryNotFoundException;
    }
}
