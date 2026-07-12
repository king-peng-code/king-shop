<?php

namespace App\Application\Identity\GetEmployee;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;

class GetEmployeeHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function handle(int $employeeId): User
    {
        return $this->repository->findById($employeeId)
            ?? throw new UserNotFoundException('员工不存在');
    }
}
