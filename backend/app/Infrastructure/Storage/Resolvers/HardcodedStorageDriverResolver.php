<?php

namespace App\Infrastructure\Storage\Resolvers;

use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;

class HardcodedStorageDriverResolver implements StorageDriverResolverInterface
{
    public function __construct(
        private readonly LocalStorageDriver $localDriver,
    ) {}

    public function resolve(): StorageDriverInterface
    {
        return $this->localDriver;
    }
}
