<?php

namespace App\Application\Identity\DisableEmployee;

use App\Domain\Identity\Exceptions\SelfModificationForbiddenException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Identity\ValueObjects\UserStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class DisableEmployeeHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function handle(int $employeeId, int $operatorId): void
    {
        if ($employeeId === $operatorId) {
            throw new SelfModificationForbiddenException('不能禁用自己的账号');
        }

        $employee = $this->repository->findById($employeeId)
            ?? throw new UserNotFoundException('员工不存在');

        if ($employee->status->isActive()) {
            $this->repository->save($employee->withStatus(UserStatus::disabled()));
            UserModel::query()->find($employeeId)?->tokens()->delete();
        }
    }
}
