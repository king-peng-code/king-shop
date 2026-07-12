<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $user = UserModel::factory()->admin()->create();

        return $user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function admin_can_create_category(): void
    {
        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/categories', ['name' => '饮品', 'sort' => 1]);

        $response->assertCreated()
            ->assertJsonPath('data.name', '饮品')
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function cannot_delete_category_with_products(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->create(['category_id' => $category->id]);

        $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 40901);
    }

    #[Test]
    public function can_delete_empty_category(): void
    {
        $category = CategoryModel::factory()->create();

        $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
