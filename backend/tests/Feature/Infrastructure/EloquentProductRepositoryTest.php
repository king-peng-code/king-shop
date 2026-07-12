<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_visible_excludes_off_sale_products(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->onSale()->create(['category_id' => $category->id, 'name' => '可见']);
        ProductModel::factory()->create(['category_id' => $category->id, 'status' => 'off_sale', 'name' => '隐藏']);

        $repo = app(ProductRepositoryInterface::class);
        $result = $repo->searchVisible(null, 1, 20);

        $this->assertSame(1, $result['total']);
        $this->assertSame('可见', $result['items'][0]->name);
    }

    #[Test]
    public function search_visible_excludes_disabled_category_products(): void
    {
        $category = CategoryModel::factory()->disabled()->create();
        ProductModel::factory()->onSale()->create(['category_id' => $category->id]);

        $repo = app(ProductRepositoryInterface::class);
        $result = $repo->searchVisible(null, 1, 20);

        $this->assertSame(0, $result['total']);
    }

    #[Test]
    public function find_visible_by_id_returns_null_for_off_sale_product(): void
    {
        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->create(['category_id' => $category->id, 'status' => 'off_sale']);

        $result = app(ProductRepositoryInterface::class)->findVisibleById($product->id);

        $this->assertNull($result);
    }

    #[Test]
    public function search_admin_filters_by_keyword(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->create(['category_id' => $category->id, 'name' => '苹果笔记本']);
        ProductModel::factory()->create(['category_id' => $category->id, 'name' => '香蕉']);

        $result = app(ProductRepositoryInterface::class)->searchAdmin(null, null, '苹果', 1, 20);

        $this->assertSame(1, $result['total']);
        $this->assertSame('苹果笔记本', $result['items'][0]->name);
    }
}
