<?php

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\ListCategories\ListCategoriesHandler;
use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\ValueObjects\CategoryStatus;
use App\Infrastructure\Cache\CategoryListCache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListCategoriesHandlerTest extends TestCase
{
    #[Test]
    public function handle_returns_cached_categories_without_querying_repository(): void
    {
        $cached = [
            'items' => [
                new Category(id: 1, name: '饮品', sort: 1, status: CategoryStatus::active()),
            ],
        ];

        $cache = $this->createMock(CategoryListCache::class);
        $cache->expects($this->once())->method('get')->willReturn($cached);

        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->never())->method('listAll');

        $handler = new ListCategoriesHandler($repository, $cache);

        $this->assertSame($cached, $handler->handle());
    }

    #[Test]
    public function handle_queries_repository_and_populates_cache_on_miss(): void
    {
        $result = [
            'items' => [
                new Category(id: 2, name: '小吃', sort: 2, status: CategoryStatus::active()),
            ],
        ];

        $cache = $this->createMock(CategoryListCache::class);
        $cache->expects($this->once())->method('get')->willReturn(null);
        $cache->expects($this->once())->method('put')->with($result);

        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('listAll')->willReturn($result);

        $handler = new ListCategoriesHandler($repository, $cache);

        $this->assertSame($result, $handler->handle());
    }
}
