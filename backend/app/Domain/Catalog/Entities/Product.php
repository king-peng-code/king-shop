<?php

namespace App\Domain\Catalog\Entities;

use App\Domain\Catalog\ValueObjects\ProductStatus;

final class Product
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $categoryId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $price,
        public readonly ?int $uploadId,
        public readonly ?string $imagePath,
        public readonly ProductStatus $status,
        public readonly int $sort,
        public readonly ?string $categoryName = null,
    ) {}
}
