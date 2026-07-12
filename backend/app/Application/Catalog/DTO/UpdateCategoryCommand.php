<?php

namespace App\Application\Catalog\DTO;

use App\Domain\Catalog\ValueObjects\CategoryStatus;

final class UpdateCategoryCommand
{
    public function __construct(
        public readonly int $categoryId,
        public readonly string $name,
        public readonly int $sort,
        public readonly CategoryStatus $status,
    ) {}
}
