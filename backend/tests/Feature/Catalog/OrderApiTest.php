<?php

namespace Tests\Feature\Catalog;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function employeeToken(?UserModel $user = null): string
    {
        $user ??= UserModel::factory()->create([
            'role' => 'employee',
            'must_change_password' => false,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function employee_can_create_order(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->onSale()->create([
            'category_id' => $category->id,
            'name' => '拿铁',
            'price' => 1500,
        ]);

        $response = $this->withToken($this->employeeToken($user))
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
                'remark' => '少糖',
                'payment_method' => 'self',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.total_amount', 3000)
            ->assertJsonPath('data.items.0.product_name', '拿铁')
            ->assertJsonPath('data.remark', '少糖');
    }

    #[Test]
    public function employee_can_list_own_orders(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $other = UserModel::factory()->create();
        $order = OrderModel::factory()->for($user, 'user')->paid()->create();
        OrderItemModel::factory()->for($order, 'order')->create(['product_name' => '拿铁']);
        OrderModel::factory()->for($other, 'user')->paid()->create();

        $this->withToken($this->employeeToken($user))
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.items.0.product_name', '拿铁');
    }

    #[Test]
    public function employee_can_cancel_pending_payment_order(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $order = OrderModel::factory()->for($user, 'user')->create(['status' => 'pending_payment']);

        $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    #[Test]
    public function employee_cannot_cancel_paid_order(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $order = OrderModel::factory()->for($user, 'user')->paid()->create();

        $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('code', 42201);
    }

    #[Test]
    public function employee_cannot_view_other_users_order(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $other = UserModel::factory()->create();
        $order = OrderModel::factory()->for($other, 'user')->paid()->create();

        $this->withToken($this->employeeToken($user))
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    #[Test]
    public function unauthenticated_cannot_create_order(): void
    {
        $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ])->assertUnauthorized();
    }

    #[Test]
    public function employee_complete_route_is_removed(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $order = OrderModel::factory()->for($user, 'user')->paid()->create();

        $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$order->id}/complete")
            ->assertNotFound();
    }
}
