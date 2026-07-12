<?php

namespace App\Domain\SystemConfig\Repositories;

use App\Domain\SystemConfig\Entities\SystemConfig;

interface SystemConfigRepositoryInterface
{
    /** @return SystemConfig[] */
    public function all(): array;

    public function findByGroupAndKey(string $group, string $key): ?SystemConfig;

    public function updateValue(string $group, string $key, string $plainValue): void;

    public function exists(string $group, string $key): bool;
}
