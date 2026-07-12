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
}
