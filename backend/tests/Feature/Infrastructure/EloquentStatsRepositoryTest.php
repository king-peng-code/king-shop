<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Statistics\Repositories\StatsRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentStatsRepository;
use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentStatsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private StatsRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentStatsRepository();
    }

    #[Test]
    public function employee_stats_returns_empty_when_no_orders(): void
    {
        UserModel::factory()->create(['role' => 'employee']);

        $result = $this->repository->getEmployeeStats();

        $this->assertSame([], $result);
    }

    #[Test]
    public function employee_stats_aggregates_correctly(): void
    {
        $employee = UserModel::factory()->create(['role' => 'employee']);
        OrderModel::factory()->count(3)->create(['user_id' => $employee->id, 'total_amount' => 1000]);

        $result = $this->repository->getEmployeeStats();

        $this->assertCount(1, $result);
        $this->assertSame($employee->id, $result[0]['user_id']);
        $this->assertSame($employee->name, $result[0]['name']);
        $this->assertSame(3, $result[0]['order_count']);
        $this->assertSame(3000, $result[0]['total_amount']);
    }

    #[Test]
    public function employee_stats_ranks_by_order_count_desc(): void
    {
        $emp1 = UserModel::factory()->create(['role' => 'employee', 'name' => 'A']);
        $emp2 = UserModel::factory()->create(['role' => 'employee', 'name' => 'B']);
        OrderModel::factory()->count(5)->create(['user_id' => $emp1->id]);
        OrderModel::factory()->count(3)->create(['user_id' => $emp2->id]);

        $result = $this->repository->getEmployeeStats();

        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0]['name']);
        $this->assertSame(5, $result[0]['order_count']);
        $this->assertSame('B', $result[1]['name']);
        $this->assertSame(3, $result[1]['order_count']);
    }

    #[Test]
    public function employee_stats_excludes_cancelled_orders(): void
    {
        $employee = UserModel::factory()->create(['role' => 'employee']);
        OrderModel::factory()->count(2)->create(['user_id' => $employee->id, 'status' => 'paid']);
        OrderModel::factory()->cancelled()->create(['user_id' => $employee->id]);

        $result = $this->repository->getEmployeeStats();

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['order_count']);
    }

    #[Test]
    public function employee_stats_excludes_non_employee_users(): void
    {
        $employee = UserModel::factory()->create(['role' => 'employee']);
        UserModel::factory()->admin()->create();
        UserModel::factory()->superAdmin()->create();
        OrderModel::factory()->create(['user_id' => $employee->id]);

        $result = $this->repository->getEmployeeStats();

        $this->assertCount(1, $result);
        $this->assertSame($employee->id, $result[0]['user_id']);
    }

    #[Test]
    public function proxy_payer_stats_returns_empty_when_no_proxy_orders(): void
    {
        ExternalUserModel::factory()->create();

        $result = $this->repository->getProxyPayerStats();

        $this->assertSame([], $result);
    }

    #[Test]
    public function proxy_payer_stats_aggregates_correctly(): void
    {
        $payer = ExternalUserModel::factory()->create();
        OrderModel::factory()->paid()->count(2)->create([
            'paid_by_external_user_id' => $payer->id,
            'payment_method' => 'proxy',
            'total_amount' => 1500,
        ]);

        $result = $this->repository->getProxyPayerStats();

        $this->assertCount(1, $result);
        $this->assertSame($payer->id, $result[0]['external_user_id']);
        $this->assertSame($payer->name, $result[0]['name']);
        $this->assertSame(2, $result[0]['order_count']);
        $this->assertSame(3000, $result[0]['total_amount']);
    }

    #[Test]
    public function proxy_payer_stats_excludes_cancelled_orders(): void
    {
        $payer = ExternalUserModel::factory()->create();
        OrderModel::factory()->paid()->create([
            'paid_by_external_user_id' => $payer->id,
            'payment_method' => 'proxy',
        ]);
        OrderModel::factory()->cancelled()->create([
            'paid_by_external_user_id' => $payer->id,
            'payment_method' => 'proxy',
        ]);

        $result = $this->repository->getProxyPayerStats();

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['order_count']);
    }

    #[Test]
    public function proxy_payer_stats_shows_null_name_as_null(): void
    {
        $payer = ExternalUserModel::factory()->create(['name' => null, 'phone' => null]);
        OrderModel::factory()->paid()->create([
            'paid_by_external_user_id' => $payer->id,
            'payment_method' => 'proxy',
        ]);

        $result = $this->repository->getProxyPayerStats();

        $this->assertNull($result[0]['name']);
        $this->assertNull($result[0]['phone']);
    }
}
