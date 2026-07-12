<?php

namespace App\Application\Identity\GetCurrentUser;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repositories\UserRepositoryInterface;

class GetCurrentUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function handle(int $userId): User
    {
        return $this->repository->findById($userId)
            ?? throw new \RuntimeException("User {$userId} not found");
    }
}
