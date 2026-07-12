<?php

namespace App\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;

class SystemConfigListCache
{
    private const string KEY = 'system:configs:grouped';

    /**
     * @return array{groups: list<array{name: string, label: string, items: list<array>}>}
     */
    public function getOrSet(callable $fallback): array
    {
        /** @var array{groups: list<array{name: string, label: string, items: list<array>}>} */
        return Cache::rememberForever(self::KEY, $fallback);
    }

    public function invalidate(): void
    {
        Cache::forget(self::KEY);
    }
}
