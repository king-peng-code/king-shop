<?php

namespace App\Application\Catalog\DTO;

final class ProductListQuery
{
    public function __construct(
        public readonly ?int $categoryId,
        public readonly ?string $status,
        public readonly string $keyword,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}
