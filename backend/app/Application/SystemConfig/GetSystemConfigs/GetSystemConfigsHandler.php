<?php

namespace App\Application\SystemConfig\GetSystemConfigs;

use App\Application\SystemConfig\ConfigGroupLabels;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class GetSystemConfigsHandler
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $repository,
    ) {}

    public function handle(): array
    {
        $grouped = [];

        foreach ($this->repository->all() as $config) {
            $grouped[$config->group][] = [
                'key' => $config->key,
                'value' => $config->displayValue(),
                'is_sensitive' => $config->isSensitive,
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
