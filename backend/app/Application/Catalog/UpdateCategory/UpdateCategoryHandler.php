<?php

namespace App\Application\Catalog\UpdateCategory;

use App\Application\Catalog\DTO\UpdateCategoryCommand;
use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\Exceptions\CategoryNotFoundException;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;

class UpdateCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
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

        return $this->repository->save($category);
    }
}
