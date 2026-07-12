<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OssStorageDriverTest extends TestCase
{
    #[Test]
    public function store_writes_file_to_s3_compatible_disk(): void
    {
        Storage::fake('oss_upload');

        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')->willReturnCallback(
            fn (string $group, string $key) => match ("{$group}.{$key}") {
                'storage.oss.bucket' => new SystemConfig('storage', 'oss.bucket', 'test-bucket', true),
                'storage.oss.endpoint' => new SystemConfig('storage', 'oss.endpoint', 'https://oss-cn-test.aliyuncs.com', true),
                'storage.oss.access_key' => new SystemConfig('storage', 'oss.access_key', 'test-key', true),
                'storage.oss.secret_key' => new SystemConfig('storage', 'oss.secret_key', 'test-secret', true),
                default => null,
            }
        );

        config([
            'filesystems.disks.oss_upload' => [
                'driver' => 's3',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'region' => 'oss-cn-test',
                'bucket' => 'test-bucket',
                'url' => 'https://test-bucket.oss-cn-test.aliyuncs.com',
                'endpoint' => 'https://oss-cn-test.aliyuncs.com',
                'use_path_style_endpoint' => false,
                'throw' => true,
            ],
        ]);

        $driver = new OssStorageDriver($repository);
        $result = $driver->store('oss-content', 'png', 'image/png');

        $this->assertStringStartsWith('uploads/', $result->path);
        $this->assertSame('oss', $result->disk);
        Storage::disk('oss_upload')->assertExists($result->path);
    }
}
