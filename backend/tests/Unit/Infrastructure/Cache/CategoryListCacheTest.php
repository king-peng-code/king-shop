<?php

namespace Tests\Unit\Infrastructure\Cache;

use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\ValueObjects\CategoryStatus;
use App\Infrastructure\Cache\CategoryListCache;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryListCacheTest extends TestCase
{
    #[Test]
    public function get_returns_null_when_cache_is_empty(): void
    {
        Cache::flush();

        $this->assertNull(app(CategoryListCache::class)->get());
    }

    #[Test]
    public function put_and_get_round_trip_category_entities(): void
    {
        Cache::flush();

        $cache = app(CategoryListCache::class);
        $data = [
            'items' => [
                new Category(id: 1, name: '饮品', sort: 1, status: CategoryStatus::active()),
                new Category(id: 2, name: '小吃', sort: 2, status: CategoryStatus::disabled()),
            ],
        ];

        $cache->put($data);

        $cached = $cache->get();

        $this->assertNotNull($cached);
        $this->assertCount(2, $cached['items']);
        $this->assertSame('饮品', $cached['items'][0]->name);
        $this->assertSame('disabled', $cached['items'][1]->status->value);
    }

    #[Test]
    public function forget_removes_cached_categories(): void
    {
        Cache::flush();

        $cache = app(CategoryListCache::class);
        $cache->put([
            'items' => [
                new Category(id: 1, name: '饮品', sort: 1, status: CategoryStatus::active()),
            ],
        ]);

        $cache->forget();

        $this->assertNull($cache->get());
    }
}
