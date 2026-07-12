<?php

namespace Tests\Unit\Application\SystemConfig;

use App\Application\SystemConfig\StoragePublicBaseUrlResolver;
use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoragePublicBaseUrlResolverTest extends TestCase
{
    #[Test]
    public function local_base_url_throws_when_config_empty(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'local.public_base_url')
            ->willReturn(new SystemConfig('storage', 'local.public_base_url', '', false));

        $resolver = new StoragePublicBaseUrlResolver($repository);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Missing storage config: storage.local.public_base_url');

        $resolver->localBaseUrl();
    }

    #[Test]
    public function local_base_url_returns_configured_value(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'local.public_base_url')
            ->willReturn(new SystemConfig('storage', 'local.public_base_url', 'https://cdn.test.com/', false));

        $resolver = new StoragePublicBaseUrlResolver($repository);

        $this->assertSame('https://cdn.test.com', $resolver->localBaseUrl());
    }

    #[Test]
    public function oss_base_url_returns_trimmed_config_value(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'oss.public_base_url')
            ->willReturn(new SystemConfig('storage', 'oss.public_base_url', 'https://cdn.example.com/', false));

        $resolver = new StoragePublicBaseUrlResolver($repository);

        $this->assertSame('https://cdn.example.com', $resolver->ossBaseUrl());
    }
}
