<?php

namespace App\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;

class CatalogCategoryListCache
{
    private const string KEY = 'catalog:categories:visible';

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Category[]}
     */
    public function getOrSet(callable $fallback): array
    {
        /** @var array{items: \App\Domain\Catalog\Entities\Category[]} */
        return Cache::remember(self::KEY, 3600, $fallback);
    }

    public function invalidate(): void
    {
        Cache::forget(self::KEY);
    }
}
