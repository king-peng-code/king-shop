<?php

namespace Tests\Unit\Application\Dashboard;

use App\Application\Dashboard\GetDashboardStats\GetDashboardStatsHandler;
use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetDashboardStatsHandlerTest extends TestCase
{
    #[Test]
    public function handle_returns_dto_from_repository(): void
    {
        $repo = $this->createMock(DashboardStatsRepositoryInterface::class);
        $repo->method('getStats')->willReturn([
            'summary' => [
                'today' => ['order_count' => 1, 'paid_order_count' => 1, 'sales_amount' => 100],
                'week' => ['order_count' => 2, 'paid_order_count' => 1, 'sales_amount' => 100],
            ],
            'status_distribution' => [['status' => 'paid', 'label' => '已支付', 'count' => 1]],
            'hot_products_by_quantity' => [],
            'hot_products_by_sales' => [],
            'week_daily_sales' => [],
        ]);

        $handler = new GetDashboardStatsHandler($repo);
        $dto = $handler->handle();

        $this->assertSame(1, $dto->toArray()['summary']['today']['order_count']);
        $this->assertSame('已支付', $dto->toArray()['status_distribution'][0]['label']);
    }
}
