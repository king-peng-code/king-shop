<?php

namespace App\Domain\Catalog\Entities;

use App\Domain\Catalog\ValueObjects\CategoryStatus;

final class Category
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly int $sort,
        public readonly CategoryStatus $status,
    ) {}
}
