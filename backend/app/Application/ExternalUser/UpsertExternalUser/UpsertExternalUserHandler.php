<?php

namespace App\Application\ExternalUser\UpsertExternalUser;

use App\Domain\ExternalUser\Entities\ExternalUser;
use App\Domain\ExternalUser\Repositories\ExternalUserRepositoryInterface;
use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;

class UpsertExternalUserHandler
{
    public function __construct(
        private readonly ExternalUserRepositoryInterface $repository,
    ) {}

    public function handle(
        ExternalUserProvider $provider,
        string $externalId,
        ?string $name = null,
        ?string $phone = null,
    ): ExternalUser {
        $existing = $this->repository->findByProviderAndExternalId($provider->value, $externalId);

        if ($existing === null) {
            $now = new \DateTimeImmutable;

            return $this->repository->save(new ExternalUser(
                id: null,
                provider: $provider,
                externalId: $externalId,
                name: $name,
                phone: $phone,
                createdAt: $now,
                updatedAt: $now,
            ));
        }

        return $this->repository->save(new ExternalUser(
            id: $existing->id,
            provider: $existing->provider,
            externalId: $existing->externalId,
            name: $name ?? $existing->name,
            phone: $phone ?? $existing->phone,
            createdAt: $existing->createdAt,
            updatedAt: new \DateTimeImmutable,
        ));
    }
}
