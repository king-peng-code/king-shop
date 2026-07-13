<?php

namespace App\Application\SystemConfig\GetSystemConfigs;

use App\Application\SystemConfig\ConfigGroupLabels;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Cache\SystemConfigListCache;

class GetSystemConfigsHandler
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $repository,
        private readonly SystemConfigListCache $cache,
    ) {}

    public function handle(bool $exposeSensitive = false): array
    {
        if ($exposeSensitive) {
            return $this->buildGrouped(exposeSensitive: true);
        }

        return $this->cache->getOrSet(fn (): array => $this->buildGrouped());
    }

    private function buildGrouped(bool $exposeSensitive = false): array
    {
        $grouped = [];

        foreach ($this->repository->all() as $config) {
            $value = $exposeSensitive
                ? $config->value
                : $config->displayValue();

            $grouped[$config->group][] = [
                'key' => $config->key,
                'value' => $value,
                'is_sensitive' => $config->isSensitive,
                'is_readonly' => SystemConfig::isReadonly($config->group, $config->key),
                'description' => $config->description,
            ];
        }

        $groups = [];
        foreach ($grouped as $name => $items) {
            $groups[] = [
                'name' => $name,
                'label' => ConfigGroupLabels::MAP[$name] ?? $name,
                'items' => $items,
            ];
        }

        return ['groups' => $groups];
    }
}
