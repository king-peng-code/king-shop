<?php

declare(strict_types=1);

namespace App\Domain\ExternalUser\Repositories;

use App\Domain\ExternalUser\Entities\ExternalUser;

interface ExternalUserRepositoryInterface
{
    public function findById(int $id): ?ExternalUser;

    public function findByProviderAndExternalId(string $provider, string $externalId): ?ExternalUser;

    /**
     * @return array{items: ExternalUser[], total: int}
     */
    public function search(string $keyword, int $page, int $perPage): array;

    public function save(ExternalUser $user): ExternalUser;
}
