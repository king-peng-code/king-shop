<?php

namespace App\Domain\ExternalUser\Entities;

use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;

final class ExternalUser
{
    public function __construct(
        public readonly ?int $id,
        public readonly ExternalUserProvider $provider,
        public readonly string $externalId,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}
}
