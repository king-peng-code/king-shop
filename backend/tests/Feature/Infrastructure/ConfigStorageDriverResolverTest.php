<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;
use App\Infrastructure\Storage\Resolvers\ConfigStorageDriverResolver;
use App\Infrastructure\Storage\Resolvers\HardcodedStorageDriverResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigStorageDriverResolverTest extends TestCase
{
    #[Test]
    public function hardcoded_resolver_always_returns_local_driver(): void
    {
        $resolver = new HardcodedStorageDriverResolver(new LocalStorageDriver);

        $this->assertInstanceOf(LocalStorageDriver::class, $resolver->resolve());
    }

    #[Test]
    public function config_resolver_returns_oss_driver_when_configured(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'driver')
            ->willReturn(new SystemConfig('storage', 'driver', 'oss', false));

        $resolver = new ConfigStorageDriverResolver(
            $repository,
            new LocalStorageDriver,
            $this->createMock(OssStorageDriver::class),
        );

        $this->assertInstanceOf(OssStorageDriver::class, $resolver->resolve());
    }

    #[Test]
    public function config_resolver_returns_local_driver_by_default(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'driver')
            ->willReturn(new SystemConfig('storage', 'driver', 'local', false));

        $resolver = new ConfigStorageDriverResolver(
            $repository,
            new LocalStorageDriver,
            $this->createMock(OssStorageDriver::class),
        );

        $this->assertInstanceOf(LocalStorageDriver::class, $resolver->resolve());
    }
}
