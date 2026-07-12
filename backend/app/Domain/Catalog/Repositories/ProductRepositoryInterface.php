<?php

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Entities\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function save(Product $product): Product;

    /**
     * @return array{items: Product[], total: int}
     */
    public function searchAdmin(?int $categoryId, ?string $status, string $keyword, int $page, int $perPage): array;

    /**
     * @return array{items: Product[], total: int}
     */
    public function searchVisible(?int $categoryId, int $page, int $perPage): array;

    public function findVisibleById(int $id): ?Product;
}
