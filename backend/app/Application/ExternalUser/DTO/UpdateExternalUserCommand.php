<?php

declare(strict_types=1);

namespace App\Application\ExternalUser\DTO;

readonly class UpdateExternalUserCommand
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $phone,
        public array $tags,
    ) {}
}
