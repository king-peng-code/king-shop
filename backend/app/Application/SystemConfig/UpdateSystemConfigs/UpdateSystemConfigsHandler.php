<?php

namespace App\Application\SystemConfig\UpdateSystemConfigs;

use App\Application\SystemConfig\DTO\SystemConfigItemDto;
use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Exceptions\SensitiveConfigForbiddenException;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Cache\SystemConfigListCache;

class UpdateSystemConfigsHandler
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $repository,
        private readonly GetSystemConfigsHandler $getHandler,
        private readonly SystemConfigListCache $configCache,
    ) {}

    /** @param SystemConfigItemDto[] $items */
    public function handle(array $items, string $actorRole): array
    {
        foreach ($items as $item) {
            if ($item->value === SystemConfig::MASK_PLACEHOLDER) {
                continue;
            }

            $existing = $this->repository->findByGroupAndKey($item->group, $item->key);

            if ($existing !== null && SystemConfig::isReadonly($item->group, $item->key)) {
                continue;
            }

            if (
                $existing !== null
                && $existing->isSensitive
                && $actorRole !== 'super_admin'
            ) {
                throw new SensitiveConfigForbiddenException;
            }

            $this->repository->updateValue($item->group, $item->key, $item->value);
        }

        $this->configCache->invalidate();

        return $this->getHandler->handle();
    }
}
