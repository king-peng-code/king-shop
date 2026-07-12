<?php

namespace App\Application\Identity\UpdateEmployee;

use App\Application\Identity\DTO\UpdateEmployeeCommand;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\ForbiddenRoleAssignmentException;
use App\Domain\Identity\Exceptions\SelfModificationForbiddenException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Domain\Identity\ValueObjects\Role;
use Illuminate\Support\Facades\Hash;

class UpdateEmployeeHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function handle(UpdateEmployeeCommand $command, int $operatorId, Role $operatorRole): User
    {
        $employee = $this->repository->findById($command->employeeId)
            ?? throw new UserNotFoundException('员工不存在');

        if (! $operatorRole->canAssignRole($command->role)) {
            throw new ForbiddenRoleAssignmentException;
        }

        if ($operatorId === $command->employeeId) {
            if (! $command->status->isActive()) {
                throw new SelfModificationForbiddenException('不能禁用自己的账号');
            }

            if ($command->role->value !== $employee->role->value) {
                throw new SelfModificationForbiddenException('不能修改自己的角色');
            }
        }

        $updated = $employee->withProfile(
            name: $command->name,
            employeeNo: $command->employeeNo,
            department: $command->department,
            role: $command->role,
            status: $command->status,
        );

        if ($command->resetPassword) {
            $updated = $updated->withPassword(
                Hash::make(config('identity.default_password')),
                true,
            );
        }

        $saved = $this->repository->save($updated);

        if (! $command->status->isActive() && $employee->status->isActive()) {
            UserModel::query()->find($command->employeeId)?->tokens()->delete();
        }

        return $saved;
    }
}
