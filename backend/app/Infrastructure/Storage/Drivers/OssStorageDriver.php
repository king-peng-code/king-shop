<?php

namespace App\Infrastructure\Storage\Drivers;

use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\ValueObjects\StoredFile;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OssStorageDriver implements StorageDriverInterface
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $configRepository,
    ) {}

    public function store(string $contents, string $extension, string $mimeType): StoredFile
    {
        $bucket = $this->configValue('oss.bucket');
        $endpoint = $this->configValue('oss.endpoint');
        $accessKey = $this->configValue('oss.access_key');
        $secretKey = $this->configValue('oss.secret_key');

        Config::set('filesystems.disks.oss_upload', [
            'driver' => 's3',
            'key' => $accessKey,
            'secret' => $secretKey,
            'region' => 'oss-cn-hangzhou',
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'url' => rtrim($endpoint, '/').'/'.$bucket,
            'use_path_style_endpoint' => false,
            'throw' => true,
        ]);

        $path = sprintf(
            'uploads/%s/%s.%s',
            now()->format('Y/m'),
            Str::uuid(),
            ltrim($extension, '.'),
        );

        $saved = Storage::disk('oss_upload')->put($path, $contents);

        if (! $saved) {
            throw new StorageException('Failed to store file on OSS disk.');
        }

        return new StoredFile(
            path: $path,
            disk: 'oss',
        );
    }

    private function configValue(string $key): string
    {
        $config = $this->configRepository->findByGroupAndKey('storage', $key);

        if ($config === null || $config->value === '') {
            throw new StorageException("Missing storage config: storage.{$key}");
        }

        return $config->value;
    }
}
