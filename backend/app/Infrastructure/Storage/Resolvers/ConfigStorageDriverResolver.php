<?php

namespace App\Infrastructure\Storage\Resolvers;

use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;

class ConfigStorageDriverResolver implements StorageDriverResolverInterface
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $configRepository,
        private readonly LocalStorageDriver $localDriver,
        private readonly OssStorageDriver $ossDriver,
    ) {}

    public function resolve(): StorageDriverInterface
    {
        $config = $this->configRepository->findByGroupAndKey('storage', 'driver');
        $driver = $config?->value ?? 'local';

        return match ($driver) {
            'oss' => $this->ossDriver,
            default => $this->localDriver,
        };
    }
}
