<?php

namespace Tests\Feature\Catalog;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function employeeToken(): string
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);

        return $user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function employee_can_list_active_categories_only(): void
    {
        CategoryModel::factory()->create(['name' => '饮品', 'sort' => 1]);
        CategoryModel::factory()->disabled()->create(['name' => '已禁用']);

        $this->withToken($this->employeeToken())
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', '饮品')
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function unauthenticated_cannot_access_categories(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
    }

    #[Test]
    public function employee_can_list_visible_products(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->onSale()->create([
            'category_id' => $category->id,
            'name' => '拿铁',
            'price' => 1500,
        ]);
        ProductModel::factory()->create([
            'category_id' => $category->id,
            'status' => 'off_sale',
            'name' => '下架商品',
        ]);

        $this->withToken($this->employeeToken())
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', '拿铁');
    }

    #[Test]
    public function off_sale_product_returns_404_on_detail(): void
    {
        $product = ProductModel::factory()->create(['status' => 'off_sale']);

        $this->withToken($this->employeeToken())
            ->getJson("/api/v1/products/{$product->id}")
            ->assertNotFound();
    }

    #[Test]
    public function disabled_category_products_not_visible(): void
    {
        $category = CategoryModel::factory()->disabled()->create();
        $product = ProductModel::factory()->onSale()->create(['category_id' => $category->id]);

        $this->withToken($this->employeeToken())
            ->getJson("/api/v1/products/{$product->id}")
            ->assertNotFound();
    }

    #[Test]
    public function products_filter_by_category_id(): void
    {
        $cat1 = CategoryModel::factory()->create();
        $cat2 = CategoryModel::factory()->create();
        ProductModel::factory()->onSale()->create(['category_id' => $cat1->id, 'name' => 'A']);
        ProductModel::factory()->onSale()->create(['category_id' => $cat2->id, 'name' => 'B']);

        $this->withToken($this->employeeToken())
            ->getJson("/api/v1/products?category_id={$cat1->id}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', 'A');
    }

    #[Test]
    public function unauthenticated_cannot_access_products(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }
}
