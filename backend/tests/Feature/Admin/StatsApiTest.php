<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return UserModel::factory()->admin()->create()->createToken('test')->plainTextToken;
    }

    #[Test]
    public function admin_can_get_employee_stats(): void
    {
        $employee = UserModel::factory()->create(['role' => 'employee']);
        OrderModel::factory()->paid()->count(3)->create(['user_id' => $employee->id]);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stats/employees')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $employee->name)
            ->assertJsonPath('data.0.order_count', 3);
    }

    #[Test]
    public function employee_stats_only_counts_paid_orders(): void
    {
        $employee = UserModel::factory()->create(['role' => 'employee']);
        OrderModel::factory()->paid()->count(2)->create(['user_id' => $employee->id]);
        OrderModel::factory()->cancelled()->create(['user_id' => $employee->id]);
        OrderModel::factory()->create(['user_id' => $employee->id]); // pending_payment

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stats/employees')
            ->assertOk()
            ->assertJsonPath('data.0.order_count', 2);
    }

    #[Test]
    public function employee_stats_only_includes_employee_role(): void
    {
        $employee = UserModel::factory()->create(['role' => 'employee']);
        $admin = UserModel::factory()->admin()->create();
        OrderModel::factory()->paid()->count(2)->create(['user_id' => $employee->id]);
        OrderModel::factory()->paid()->create(['user_id' => $admin->id]);

        $response = $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stats/employees');

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($employee->name, $response->json('data.0.name'));
    }

    #[Test]
    public function admin_can_get_proxy_payer_stats(): void
    {
        $payer = ExternalUserModel::factory()->create();
        OrderModel::factory()->paid()->count(2)->create([
            'paid_by_external_user_id' => $payer->id,
            'payment_method' => 'proxy',
        ]);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stats/proxy-payers')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $payer->name)
            ->assertJsonPath('data.0.order_count', 2);
    }

    #[Test]
    public function proxy_payer_stats_only_counts_paid_orders(): void
    {
        $payer = ExternalUserModel::factory()->create();
        OrderModel::factory()->paid()->count(2)->create([
            'paid_by_external_user_id' => $payer->id,
            'payment_method' => 'proxy',
        ]);
        OrderModel::factory()->cancelled()->create([
            'paid_by_external_user_id' => $payer->id,
            'payment_method' => 'proxy',
        ]);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stats/proxy-payers')
            ->assertOk()
            ->assertJsonPath('data.0.order_count', 2);
    }

    #[Test]
    public function employee_cannot_access_stats(): void
    {
        $token = UserModel::factory()->create(['role' => 'employee'])->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/stats/employees')
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/v1/admin/stats/proxy-payers')
            ->assertForbidden();
    }

    #[Test]
    public function guest_cannot_access_stats(): void
    {
        $this->getJson('/api/v1/admin/stats/employees')->assertUnauthorized();
        $this->getJson('/api/v1/admin/stats/proxy-payers')->assertUnauthorized();
    }
}
