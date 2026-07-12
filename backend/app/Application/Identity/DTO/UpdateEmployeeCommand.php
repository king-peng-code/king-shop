<?php

namespace App\Application\Identity\DTO;

use App\Domain\Identity\ValueObjects\Role;
use App\Domain\Identity\ValueObjects\UserStatus;

final class UpdateEmployeeCommand
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $name,
        public readonly ?string $employeeNo,
        public readonly ?string $department,
        public readonly Role $role,
        public readonly UserStatus $status,
        public readonly bool $resetPassword,
    ) {}
}
