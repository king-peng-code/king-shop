<?php

namespace App\Application\Catalog\DTO;

use App\Domain\Catalog\ValueObjects\ProductStatus;

final class UpdateProductCommand
{
    public function __construct(
        public readonly int $productId,
        public readonly int $categoryId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $price,
        public readonly ?int $uploadId,
        public readonly ?string $imagePath,
        public readonly ProductStatus $status,
        public readonly int $sort,
    ) {}
}
