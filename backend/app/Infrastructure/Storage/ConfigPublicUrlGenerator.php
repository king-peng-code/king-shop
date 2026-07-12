<?php

namespace App\Infrastructure\Storage;

use App\Application\SystemConfig\StoragePublicBaseUrlResolver;
use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\Storage\Services\PublicUrlGeneratorInterface;

class ConfigPublicUrlGenerator implements PublicUrlGeneratorInterface
{
    public function __construct(
        private readonly StoragePublicBaseUrlResolver $publicBaseUrlResolver,
    ) {}

    public function generate(string $path, string $disk): string
    {
        $path = ltrim($path, '/');

        return match ($disk) {
            'oss' => $this->ossPublicUrl($path),
            default => $this->publicBaseUrlResolver->localBaseUrl().'/storage/'.$path,
        };
    }

    private function ossPublicUrl(string $path): string
    {
        $baseUrl = $this->publicBaseUrlResolver->ossBaseUrl();

        if ($baseUrl === '') {
            throw new StorageException('Missing storage config: storage.oss.public_base_url');
        }

        return $baseUrl.'/'.$path;
    }
}
