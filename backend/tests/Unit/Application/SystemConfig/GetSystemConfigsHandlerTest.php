<?php

namespace Tests\Unit\Application\SystemConfig;

use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Cache\SystemConfigListCache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetSystemConfigsHandlerTest extends TestCase
{
    private function createHandler(SystemConfigRepositoryInterface $repository): GetSystemConfigsHandler
    {
        $cache = $this->createMock(SystemConfigListCache::class);
        $cache->method('getOrSet')->willReturnCallback(
            fn (callable $fallback) => $fallback(),
        );

        return new GetSystemConfigsHandler($repository, $cache);
    }

    #[Test]
    public function handle_groups_configs_and_masks_sensitive_values(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('all')->willReturn([
            new SystemConfig('app', 'name', '内部下午茶', false, '商城名称'),
            new SystemConfig('payment', 'wechat.mch_id', '1234567890', true, '微信商户号'),
        ]);

        $handler = $this->createHandler($repository);
        $result = $handler->handle();

        $this->assertArrayHasKey('groups', $result);
        $this->assertCount(2, $result['groups']);

        $appGroup = collect($result['groups'])->firstWhere('name', 'app');
        $this->assertSame('基础信息', $appGroup['label']);
        $this->assertSame('内部下午茶', $appGroup['items'][0]['value']);

        $paymentGroup = collect($result['groups'])->firstWhere('name', 'payment');
        $this->assertSame('****', $paymentGroup['items'][0]['value']);
        $this->assertTrue($paymentGroup['items'][0]['is_sensitive']);
    }

    #[Test]
    public function handle_shows_empty_local_public_base_url_when_unset(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('all')->willReturn([
            new SystemConfig('storage', 'local.public_base_url', '', false, '图片公开访问域名'),
        ]);

        $handler = $this->createHandler($repository);
        $result = $handler->handle();

        $storageGroup = collect($result['groups'])->firstWhere('name', 'storage');
        $item = $storageGroup['items'][0];

        $this->assertSame('', $item['value']);
        $this->assertFalse($item['is_readonly']);
    }

    #[Test]
    public function handle_shows_stored_local_public_base_url_when_set(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('all')->willReturn([
            new SystemConfig('storage', 'local.public_base_url', 'https://api.test.com', false, '图片公开访问域名'),
        ]);

        $handler = $this->createHandler($repository);
        $result = $handler->handle();

        $storageGroup = collect($result['groups'])->firstWhere('name', 'storage');
        $item = $storageGroup['items'][0];

        $this->assertSame('https://api.test.com', $item['value']);
        $this->assertFalse($item['is_readonly']);
    }
}
