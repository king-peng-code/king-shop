<?php

namespace Tests\Unit\Application\SystemConfig;

use App\Application\SystemConfig\DTO\SystemConfigItemDto;
use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Application\SystemConfig\UpdateSystemConfigs\UpdateSystemConfigsHandler;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Exceptions\SensitiveConfigForbiddenException;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateSystemConfigsHandlerTest extends TestCase
{
    #[Test]
    public function handle_updates_values_and_skips_mask_placeholder(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateValue')
            ->with('app', 'name', '内部晚餐');

        $getHandler = $this->createMock(GetSystemConfigsHandler::class);
        $getHandler->method('handle')->willReturn(['groups' => []]);

        $handler = new UpdateSystemConfigsHandler($repository, $getHandler);

        $result = $handler->handle(
            [
                new SystemConfigItemDto('app', 'name', '内部晚餐'),
                new SystemConfigItemDto('payment', 'wechat.mch_id', SystemConfig::MASK_PLACEHOLDER),
            ],
            'super_admin',
        );

        $this->assertSame(['groups' => []], $result);
    }

    #[Test]
    public function super_admin_can_update_sensitive_config_value(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByGroupAndKey')
            ->with('payment', 'wechat.mch_id')
            ->willReturn(new SystemConfig('payment', 'wechat.mch_id', 'old', true, '微信商户号'));
        $repository->expects($this->once())
            ->method('updateValue')
            ->with('payment', 'wechat.mch_id', 'new-value');

        $getHandler = $this->createMock(GetSystemConfigsHandler::class);
        $getHandler->method('handle')->willReturn(['groups' => []]);

        $handler = new UpdateSystemConfigsHandler($repository, $getHandler);

        $result = $handler->handle(
            [new SystemConfigItemDto('payment', 'wechat.mch_id', 'new-value')],
            'super_admin',
        );

        $this->assertSame(['groups' => []], $result);
    }

    #[Test]
    public function admin_cannot_update_sensitive_config_value(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('payment', 'wechat.mch_id')
            ->willReturn(new SystemConfig('payment', 'wechat.mch_id', 'old', true, '微信商户号'));

        $getHandler = $this->createMock(GetSystemConfigsHandler::class);
        $handler = new UpdateSystemConfigsHandler($repository, $getHandler);

        $this->expectException(SensitiveConfigForbiddenException::class);

        $handler->handle(
            [new SystemConfigItemDto('payment', 'wechat.mch_id', 'new-value')],
            'admin',
        );
    }
}
