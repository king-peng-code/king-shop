<?php

namespace App\Application\Identity\DTO;

use App\Domain\Identity\Entities\User;

final class LoginResultDto
{
    public function __construct(
        public readonly string $token,
        public readonly User $user,
        public readonly bool $mustChangePassword,
    ) {}
}
