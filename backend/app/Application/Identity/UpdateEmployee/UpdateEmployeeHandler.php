<?php

namespace App\Application\Identity\UpdateEmployee;

use App\Application\Identity\DTO\UpdateEmployeeCommand;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\ForbiddenRoleAssignmentException;
use App\Domain\Identity\Exceptions\SelfModificationForbiddenException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
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
            ?? throw new \RuntimeException("Employee {$command->employeeId} not found");

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

        return $this->repository->save($updated);
    }
}
