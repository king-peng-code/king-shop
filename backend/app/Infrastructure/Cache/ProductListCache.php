<?php

namespace App\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;

class ProductListCache
{
    private const string VERSION_KEY = 'catalog:product:version';

    private const int TTL_SECONDS = 600;

    /**
     * @return array{items: \App\Domain\Catalog\Entities\Product[], total: int}
     */
    public function getOrSet(string $type, ?int $categoryId, ?string $status, int $page, int $perPage, callable $fallback): array
    {
        $key = $this->buildKey($type, $categoryId, $status, $page, $perPage);

        return Cache::remember($key, self::TTL_SECONDS, $fallback);
    }

    public function invalidateAll(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    private function buildKey(string $type, ?int $categoryId, ?string $status, int $page, int $perPage): string
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);
        $cat = $categoryId !== null ? (string) $categoryId : 'all';
        $st = $status ?? 'all';

        return "catalog:product:{$type}:v{$version}:{$cat}:{$st}:p{$page}:pp{$perPage}";
    }
}
