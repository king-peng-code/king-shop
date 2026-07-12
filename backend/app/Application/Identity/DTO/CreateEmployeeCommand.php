<?php

namespace App\Application\Identity\DTO;

use App\Domain\Identity\ValueObjects\Role;

final class CreateEmployeeCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly Role $role,
    ) {}
}
