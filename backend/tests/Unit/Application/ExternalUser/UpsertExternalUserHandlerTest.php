<?php

namespace Tests\Unit\Application\ExternalUser;

use App\Application\ExternalUser\UpsertExternalUser\UpsertExternalUserHandler;
use App\Domain\ExternalUser\Entities\ExternalUser;
use App\Domain\ExternalUser\Repositories\ExternalUserRepositoryInterface;
use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpsertExternalUserHandlerTest extends TestCase
{
    #[Test]
    public function test_creates_external_user_when_not_exists(): void
    {
        $repo = $this->createMock(ExternalUserRepositoryInterface::class);
        $repo->method('findByProviderAndExternalId')->willReturn(null);
        $repo->expects($this->once())->method('save')->willReturnCallback(fn ($u) => new ExternalUser(
            id: 1,
            provider: ExternalUserProvider::fromString('fake'),
            externalId: 'uuid-1',
            name: '测试代付人',
            phone: null,
            createdAt: new \DateTimeImmutable,
            updatedAt: new \DateTimeImmutable,
        ));

        $handler = new UpsertExternalUserHandler($repo);
        $result = $handler->handle(
            ExternalUserProvider::fromString('fake'),
            'uuid-1',
            '测试代付人',
        );

        $this->assertSame(1, $result->id);
        $this->assertSame('测试代付人', $result->name);
    }

    #[Test]
    public function test_updates_name_when_user_already_exists(): void
    {
        $existing = new ExternalUser(
            id: 5,
            provider: ExternalUserProvider::fromString('wechat'),
            externalId: 'oABC',
            name: null,
            phone: null,
            createdAt: new \DateTimeImmutable,
            updatedAt: new \DateTimeImmutable,
        );

        $repo = $this->createMock(ExternalUserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByProviderAndExternalId')
            ->with('wechat', 'oABC')
            ->willReturn($existing);
        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ExternalUser $user): bool {
                return $user->id === 5
                    && $user->name === '李四'
                    && $user->phone === null;
            }))
            ->willReturnCallback(fn (ExternalUser $user) => $user);

        $handler = new UpsertExternalUserHandler($repo);
        $result = $handler->handle(
            ExternalUserProvider::fromString('wechat'),
            'oABC',
            '李四',
        );

        $this->assertSame(5, $result->id);
        $this->assertSame('李四', $result->name);
    }
}
