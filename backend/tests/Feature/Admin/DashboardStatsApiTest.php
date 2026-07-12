<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardStatsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return UserModel::factory()->admin()->create()->createToken('test')->plainTextToken;
    }

    #[Test]
    public function admin_can_get_dashboard_stats(): void
    {
        OrderModel::factory()->paid()->create();

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['today', 'week'],
                    'status_distribution',
                    'hot_products_by_quantity',
                    'hot_products_by_sales',
                    'week_daily_sales',
                ],
            ]);
    }

    #[Test]
    public function employee_cannot_access_dashboard_stats(): void
    {
        $token = UserModel::factory()->create(['role' => 'employee'])->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertForbidden();
    }

    #[Test]
    public function guest_cannot_access_dashboard_stats(): void
    {
        $this->getJson('/api/v1/admin/dashboard/stats')->assertUnauthorized();
    }
}
