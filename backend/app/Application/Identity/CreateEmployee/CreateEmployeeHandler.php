<?php

namespace App\Application\Identity\CreateEmployee;

use App\Application\Identity\DTO\CreateEmployeeCommand;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\ForbiddenRoleAssignmentException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Identity\ValueObjects\Role;
use App\Domain\Identity\ValueObjects\UserStatus;
use Illuminate\Support\Facades\Hash;

class CreateEmployeeHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function handle(CreateEmployeeCommand $command, Role $operatorRole): User
    {
        if (! $operatorRole->canAssignRole($command->role)) {
            throw new ForbiddenRoleAssignmentException;
        }

        $user = new User(
            id: null,
            name: $command->name,
            email: null,
            phone: $command->phone,
            employeeNo: $command->employeeNo,
            department: $command->department,
            role: $command->role,
            status: UserStatus::active(),
            avatar: null,
            mustChangePassword: true,
            passwordHash: Hash::make(config('identity.default_password')),
        );

        return $this->repository->save($user);
    }
}
