<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentDashboardStatsRepository;
use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentDashboardStatsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStatsRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-12 14:30:00', 'Asia/Shanghai'));
        $this->repository = new EloquentDashboardStatsRepository();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function summary_counts_today_and_week_correctly(): void
    {
        $user = UserModel::factory()->create();

        // 今日：1 待支付 + 1 已支付 6000 分
        OrderModel::factory()->for($user, 'user')->create([
            'created_at' => Carbon::parse('2026-07-12 10:00:00', 'Asia/Shanghai'),
        ]);
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 6000,
            'paid_at' => Carbon::parse('2026-07-12 11:00:00', 'Asia/Shanghai'),
            'created_at' => Carbon::parse('2026-07-12 11:00:00', 'Asia/Shanghai'),
        ]);

        // 本周早些时候：1 已支付 3000 分
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 3000,
            'paid_at' => Carbon::parse('2026-07-07 09:00:00', 'Asia/Shanghai'),
            'created_at' => Carbon::parse('2026-07-07 09:00:00', 'Asia/Shanghai'),
        ]);

        // 上周：不计入本周
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 9999,
            'paid_at' => Carbon::parse('2026-07-05 09:00:00', 'Asia/Shanghai'),
            'created_at' => Carbon::parse('2026-07-05 09:00:00', 'Asia/Shanghai'),
        ]);

        $stats = $this->repository->getStats();

        $this->assertSame(2, $stats['summary']['today']['order_count']);
        $this->assertSame(1, $stats['summary']['today']['paid_order_count']);
        $this->assertSame(6000, $stats['summary']['today']['sales_amount']);
        $this->assertSame(3, $stats['summary']['week']['order_count']);
        $this->assertSame(2, $stats['summary']['week']['paid_order_count']);
        $this->assertSame(9000, $stats['summary']['week']['sales_amount']);
    }

    #[Test]
    public function hot_products_rank_by_quantity_and_sales(): void
    {
        $user = UserModel::factory()->create();
        $latte = ProductModel::factory()->create(['name' => '拿铁', 'price' => 1500]);
        $mocha = ProductModel::factory()->create(['name' => '摩卡', 'price' => 2000]);

        $order = OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 11000,
            'paid_at' => Carbon::parse('2026-07-10 12:00:00', 'Asia/Shanghai'),
        ]);

        OrderItemModel::factory()->for($order, 'order')->create([
            'product_id' => $latte->id,
            'product_name' => '拿铁',
            'price' => 1500,
            'quantity' => 5,
            'subtotal' => 7500,
        ]);
        OrderItemModel::factory()->for($order, 'order')->create([
            'product_id' => $mocha->id,
            'product_name' => '摩卡',
            'price' => 2000,
            'quantity' => 2,
            'subtotal' => 4000,
        ]);

        $stats = $this->repository->getStats();

        $this->assertSame('拿铁', $stats['hot_products_by_quantity'][0]['product_name']);
        $this->assertSame(5, $stats['hot_products_by_quantity'][0]['quantity']);
        $this->assertSame('拿铁', $stats['hot_products_by_sales'][0]['product_name']);
        $this->assertSame(7500, $stats['hot_products_by_sales'][0]['sales_amount']);
    }

    #[Test]
    public function status_distribution_counts_all_orders(): void
    {
        $user = UserModel::factory()->create();
        OrderModel::factory()->for($user, 'user')->paid()->count(2)->create();
        OrderModel::factory()->for($user, 'user')->cancelled()->create();

        $stats = $this->repository->getStats();
        $byStatus = collect($stats['status_distribution'])->keyBy('status');

        $this->assertSame(2, $byStatus['paid']['count']);
        $this->assertSame(1, $byStatus['cancelled']['count']);
        $this->assertSame('已支付', $byStatus['paid']['label']);
    }

    #[Test]
    public function early_morning_shanghai_paid_order_counts_in_today(): void
    {
        $user = UserModel::factory()->create();

        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 5000,
            'paid_at' => Carbon::parse('2026-07-12 01:00:00', 'Asia/Shanghai'),
            'created_at' => Carbon::parse('2026-07-12 01:00:00', 'Asia/Shanghai'),
        ]);

        $stats = $this->repository->getStats();

        $this->assertSame(1, $stats['summary']['today']['order_count']);
        $this->assertSame(1, $stats['summary']['today']['paid_order_count']);
        $this->assertSame(5000, $stats['summary']['today']['sales_amount']);

        $byDate = collect($stats['week_daily_sales'])->keyBy('date');
        $this->assertSame(5000, $byDate['2026-07-12']['sales_amount']);
    }

    #[Test]
    public function week_daily_sales_fills_missing_days_with_zero(): void
    {
        $user = UserModel::factory()->create();
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 3000,
            'paid_at' => Carbon::parse('2026-07-10 12:00:00', 'Asia/Shanghai'),
        ]);

        $stats = $this->repository->getStats();

        $this->assertCount(7, $stats['week_daily_sales']);
        $byDate = collect($stats['week_daily_sales'])->keyBy('date');
        $this->assertSame(3000, $byDate['2026-07-10']['sales_amount']);
        $this->assertSame(0, $byDate['2026-07-08']['sales_amount']);
    }
}
