<?php

namespace App\Domain\ExternalUser\Repositories;

use App\Domain\ExternalUser\Entities\ExternalUser;

interface ExternalUserRepositoryInterface
{
    public function findByProviderAndExternalId(string $provider, string $externalId): ?ExternalUser;

    public function save(ExternalUser $user): ExternalUser;
}
