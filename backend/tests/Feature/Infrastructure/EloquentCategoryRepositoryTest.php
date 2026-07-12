<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\ValueObjects\CategoryStatus;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function find_by_id_returns_category_entity(): void
    {
        $model = CategoryModel::factory()->create(['name' => '办公用品']);

        $category = app(CategoryRepositoryInterface::class)->findById($model->id);

        $this->assertNotNull($category);
        $this->assertSame('办公用品', $category->name);
        $this->assertTrue($category->status->isActive());
    }

    #[Test]
    public function list_active_excludes_disabled_categories(): void
    {
        CategoryModel::factory()->create(['name' => '启用']);
        CategoryModel::factory()->disabled()->create(['name' => '禁用']);

        $result = app(CategoryRepositoryInterface::class)->listActive();

        $this->assertCount(1, $result['items']);
        $this->assertSame('启用', $result['items'][0]->name);
    }

    #[Test]
    public function count_products_returns_product_count_for_category(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->count(2)->create(['category_id' => $category->id]);

        $count = app(CategoryRepositoryInterface::class)->countProducts($category->id);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function save_persists_new_category_and_returns_entity_with_id(): void
    {
        $category = new Category(
            id: null,
            name: '新分类',
            sort: 10,
            status: CategoryStatus::active(),
        );

        $saved = app(CategoryRepositoryInterface::class)->save($category);

        $this->assertNotNull($saved->id);
        $this->assertDatabaseHas('categories', [
            'id' => $saved->id,
            'name' => '新分类',
            'sort' => 10,
            'status' => 'active',
        ]);
    }
}
