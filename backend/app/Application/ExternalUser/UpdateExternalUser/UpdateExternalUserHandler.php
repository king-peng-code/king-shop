<?php

declare(strict_types=1);

namespace App\Application\ExternalUser\UpdateExternalUser;

use App\Application\ExternalUser\DTO\UpdateExternalUserCommand;
use App\Domain\ExternalUser\Repositories\ExternalUserRepositoryInterface;

class UpdateExternalUserHandler
{
    public function __construct(
        private readonly ExternalUserRepositoryInterface $repository,
    ) {}

    public function handle(UpdateExternalUserCommand $command): void
    {
        $user = $this->repository->findById($command->id);

        if ($user === null) {
            throw new \RuntimeException('代付人不存在');
        }

        $updated = $user->withProfile(
            name: $command->name,
            phone: $command->phone,
            tags: $command->tags,
        );

        $this->repository->save($updated);
    }
}
