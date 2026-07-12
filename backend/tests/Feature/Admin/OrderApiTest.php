<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $user = UserModel::factory()->admin()->create();

        return $user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function admin_can_list_orders_filtered_by_status(): void
    {
        OrderModel::factory()->paid()->create();
        OrderModel::factory()->create(['status' => 'cancelled']);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function admin_fulfillment_routes_are_removed(): void
    {
        $order = OrderModel::factory()->paid()->create();

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/admin/orders/{$order->id}/preparing")
            ->assertNotFound();

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/admin/orders/{$order->id}/ready")
            ->assertNotFound();

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/admin/orders/{$order->id}/complete")
            ->assertNotFound();
    }

    #[Test]
    public function admin_cannot_cancel_paid_order(): void
    {
        $order = OrderModel::factory()->paid()->create();

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/admin/orders/{$order->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('code', 42201);
    }

    #[Test]
    public function proxy_order_detail_includes_paid_by_user(): void
    {
        $payer = UserModel::factory()->create(['name' => '李四']);
        $order = OrderModel::factory()->paid()->create([
            'payment_method' => 'proxy',
            'paid_by_user_id' => $payer->id,
        ]);

        $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.paid_by_user.name', '李四');
    }
}
