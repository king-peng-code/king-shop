<?php

namespace App\Application\Catalog\DTO;

use App\Domain\Catalog\ValueObjects\CategoryStatus;

final class CreateCategoryCommand
{
    public function __construct(
        public readonly string $name,
        public readonly int $sort,
        public readonly CategoryStatus $status,
    ) {}
}
