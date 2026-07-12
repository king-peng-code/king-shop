<?php

namespace Tests\Unit\Infrastructure\Storage;

use App\Application\SystemConfig\StoragePublicBaseUrlResolver;
use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Storage\ConfigPublicUrlGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigPublicUrlGeneratorTest extends TestCase
{
    #[Test]
    public function local_disk_prepends_public_base_url_with_storage_prefix(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'local.public_base_url')
            ->willReturn(new SystemConfig(
                'storage',
                'local.public_base_url',
                'http://localhost:8000',
                false,
            ));

        $resolver = new StoragePublicBaseUrlResolver($repository);
        $generator = new ConfigPublicUrlGenerator($resolver);
        $url = $generator->generate('uploads/2026/07/abc.jpg', 'local');

        $this->assertSame(
            'http://localhost:8000/storage/uploads/2026/07/abc.jpg',
            $url,
        );
    }

    #[Test]
    public function oss_disk_prepends_public_base_url_from_system_config(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->willReturnCallback(function (string $group, string $key) {
                if ($group === 'storage' && $key === 'oss.public_base_url') {
                    return new SystemConfig(
                        'storage',
                        'oss.public_base_url',
                        'https://cdn.example.com',
                        false,
                        '图片公开访问域名',
                    );
                }

                return null;
            });

        $resolver = new StoragePublicBaseUrlResolver($repository);
        $generator = new ConfigPublicUrlGenerator($resolver);
        $url = $generator->generate('uploads/2026/07/abc.jpg', 'oss');

        $this->assertSame(
            'https://cdn.example.com/uploads/2026/07/abc.jpg',
            $url,
        );
    }

    #[Test]
    public function local_disk_throws_when_public_base_url_missing(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'local.public_base_url')
            ->willReturn(null);

        $resolver = new StoragePublicBaseUrlResolver($repository);
        $generator = new ConfigPublicUrlGenerator($resolver);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Missing storage config: storage.local.public_base_url');

        $generator->generate('uploads/2026/07/abc.jpg', 'local');
    }

    #[Test]
    public function oss_disk_throws_when_public_base_url_missing(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->willReturn(null);

        $resolver = new StoragePublicBaseUrlResolver($repository);
        $generator = new ConfigPublicUrlGenerator($resolver);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Missing storage config: storage.oss.public_base_url');

        $generator->generate('uploads/2026/07/abc.jpg', 'oss');
    }
}
