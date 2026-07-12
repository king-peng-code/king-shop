<?php

namespace App\Application\SystemConfig;

use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class StoragePublicBaseUrlResolver
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $configRepository,
    ) {}

    public function localBaseUrl(): string
    {
        $config = $this->configRepository->findByGroupAndKey('storage', 'local.public_base_url');
        $stored = rtrim($config?->value ?? '', '/');

        if ($stored === '') {
            throw new StorageException('Missing storage config: storage.local.public_base_url');
        }

        return $stored;
    }

    public function ossBaseUrl(): string
    {
        $config = $this->configRepository->findByGroupAndKey('storage', 'oss.public_base_url');

        return rtrim($config?->value ?? '', '/');
    }
}
