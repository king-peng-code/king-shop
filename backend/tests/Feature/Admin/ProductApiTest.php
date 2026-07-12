<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLocalStoragePublicBaseUrl();
    }

    private function adminToken(): string
    {
        $user = UserModel::factory()->admin()->create();

        return $user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function admin_can_create_product_with_upload_id(): void
    {
        $category = CategoryModel::factory()->create();
        $upload = UploadModel::factory()->create(['path' => 'uploads/2026/07/p.jpg']);

        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/products', [
                'category_id' => $category->id,
                'name' => '拿铁',
                'price' => 1500,
                'upload_id' => $upload->id,
                'status' => 'on_sale',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', '拿铁')
            ->assertJsonPath('data.price', 1500)
            ->assertJsonPath('data.image_path', 'uploads/2026/07/p.jpg');
    }

    #[Test]
    public function admin_products_have_no_delete_route(): void
    {
        $product = ProductModel::factory()->create();

        $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/products/{$product->id}")
            ->assertMethodNotAllowed();
    }

    #[Test]
    public function admin_can_update_product_preserving_image_when_image_fields_omitted(): void
    {
        $category = CategoryModel::factory()->create(['name' => '饮品']);
        $upload = UploadModel::factory()->create(['path' => 'uploads/2026/07/existing.jpg']);
        $product = ProductModel::factory()->create([
            'category_id' => $category->id,
            'name' => '拿铁',
            'price' => 1500,
            'upload_id' => $upload->id,
            'image_path' => 'uploads/2026/07/existing.jpg',
            'status' => 'on_sale',
            'sort' => 1,
        ]);

        $response = $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'category_id' => $category->id,
                'name' => '拿铁大杯',
                'price' => 1800,
                'status' => 'on_sale',
                'sort' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', '拿铁大杯')
            ->assertJsonPath('data.price', 1800)
            ->assertJsonPath('data.upload_id', $upload->id)
            ->assertJsonPath('data.image_path', 'uploads/2026/07/existing.jpg')
            ->assertJsonPath('data.category_name', '饮品');
    }

    #[Test]
    public function admin_can_list_products_filtered_by_category(): void
    {
        $categoryA = CategoryModel::factory()->create(['name' => '饮品']);
        $categoryB = CategoryModel::factory()->create(['name' => '甜点']);
        ProductModel::factory()->create(['category_id' => $categoryA->id, 'name' => '拿铁']);
        ProductModel::factory()->create(['category_id' => $categoryB->id, 'name' => '蛋糕']);

        $response = $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/products?category_id={$categoryA->id}");

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', '拿铁')
            ->assertJsonPath('data.items.0.category_name', '饮品');
    }

    #[Test]
    public function admin_can_show_product_with_category_name(): void
    {
        $category = CategoryModel::factory()->create(['name' => '饮品']);
        $product = ProductModel::factory()->create([
            'category_id' => $category->id,
            'name' => '拿铁',
        ]);

        $response = $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', '拿铁')
            ->assertJsonPath('data.category_name', '饮品');
    }
}
