<?php

namespace Tests\Unit\Application\Statistics;

use App\Application\Statistics\GetEmployeeStats\GetEmployeeStatsHandler;
use App\Domain\Statistics\Repositories\StatsRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetEmployeeStatsHandlerTest extends TestCase
{
    #[Test]
    public function handle_returns_dto_from_repository(): void
    {
        $repo = $this->createMock(StatsRepositoryInterface::class);
        $repo->method('getEmployeeStats')->willReturn([
            [
                'user_id' => 1,
                'name' => '张三',
                'phone' => '13800138000',
                'order_count' => 5,
                'total_amount' => 15000,
            ],
            [
                'user_id' => 2,
                'name' => '李四',
                'phone' => '13900139000',
                'order_count' => 3,
                'total_amount' => 9000,
            ],
        ]);

        $handler = new GetEmployeeStatsHandler($repo);
        $result = $handler->handle()->toArray();

        $this->assertCount(2, $result);
        $this->assertSame(5, $result[0]['order_count']);
        $this->assertSame(15000, $result[0]['total_amount']);
        $this->assertSame('李四', $result[1]['name']);
    }

    #[Test]
    public function handle_returns_empty_array_when_no_employees(): void
    {
        $repo = $this->createMock(StatsRepositoryInterface::class);
        $repo->method('getEmployeeStats')->willReturn([]);

        $handler = new GetEmployeeStatsHandler($repo);
        $result = $handler->handle()->toArray();

        $this->assertSame([], $result);
    }
}
