<?php

namespace Tests\Unit\Application\Statistics;

use App\Application\Statistics\GetProxyPayerStats\GetProxyPayerStatsHandler;
use App\Domain\Statistics\Repositories\StatsRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetProxyPayerStatsHandlerTest extends TestCase
{
    #[Test]
    public function handle_returns_dto_from_repository(): void
    {
        $repo = $this->createMock(StatsRepositoryInterface::class);
        $repo->method('getProxyPayerStats')->willReturn([
            [
                'external_user_id' => 1,
                'name' => '王五',
                'phone' => '13700137000',
                'provider' => 'alipay',
                'order_count' => 8,
                'total_amount' => 24000,
            ],
        ]);

        $handler = new GetProxyPayerStatsHandler($repo);
        $result = $handler->handle()->toArray();

        $this->assertCount(1, $result);
        $this->assertSame('alipay', $result[0]['provider']);
        $this->assertSame(8, $result[0]['order_count']);
        $this->assertSame(24000, $result[0]['total_amount']);
    }

    #[Test]
    public function handle_returns_empty_array_when_no_proxy_payers(): void
    {
        $repo = $this->createMock(StatsRepositoryInterface::class);
        $repo->method('getProxyPayerStats')->willReturn([]);

        $handler = new GetProxyPayerStatsHandler($repo);
        $result = $handler->handle()->toArray();

        $this->assertSame([], $result);
    }
}
