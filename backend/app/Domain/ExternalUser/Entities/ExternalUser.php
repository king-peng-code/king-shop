<?php

declare(strict_types=1);

namespace App\Domain\ExternalUser\Entities;

use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;

final class ExternalUser
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ExternalUserProvider $provider,
        public readonly string $externalId,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly array $tags,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string[] $tags
     */
    public function withProfile(?string $name, ?string $phone, array $tags): self
    {
        return new self(
            $this->id,
            $this->provider,
            $this->externalId,
            $name,
            $phone,
            $tags,
            $this->createdAt,
            $this->updatedAt,
        );
    }
}
