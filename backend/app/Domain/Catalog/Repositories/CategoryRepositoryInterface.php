<?php

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Entities\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    public function save(Category $category): Category;

    public function delete(int $id): void;

    /**
     * @return array{items: Category[]}
     */
    public function listAll(): array;

    /**
     * @return array{items: Category[]}
     */
    public function listActive(): array;

    public function countProducts(int $categoryId): int;
}
