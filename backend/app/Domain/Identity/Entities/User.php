<?php

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\ValueObjects\Role;
use App\Domain\Identity\ValueObjects\UserStatus;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly string $phone,
        public readonly ?string $employeeNo,
        public readonly ?string $department,
        public readonly Role $role,
        public readonly UserStatus $status,
        public readonly ?string $avatar,
        public readonly bool $mustChangePassword,
        public readonly string $passwordHash,
    ) {}

    public function canLogin(): bool
    {
        return $this->status->isActive();
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->name,
            $this->email,
            $this->phone,
            $this->employeeNo,
            $this->department,
            $this->role,
            $this->status,
            $this->avatar,
            $this->mustChangePassword,
            $this->passwordHash,
        );
    }

    public function withProfile(
        string $name,
        ?string $employeeNo,
        ?string $department,
        Role $role,
        UserStatus $status,
    ): self {
        return new self(
            $this->id,
            $name,
            $this->email,
            $this->phone,
            $employeeNo,
            $department,
            $role,
            $status,
            $this->avatar,
            $this->mustChangePassword,
            $this->passwordHash,
        );
    }

    public function withPassword(string $passwordHash, bool $mustChangePassword): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->email,
            $this->phone,
            $this->employeeNo,
            $this->department,
            $this->role,
            $this->status,
            $this->avatar,
            $mustChangePassword,
            $passwordHash,
        );
    }

    public function withMustChangePassword(bool $mustChangePassword): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->email,
            $this->phone,
            $this->employeeNo,
            $this->department,
            $this->role,
            $this->status,
            $this->avatar,
            $mustChangePassword,
            $this->passwordHash,
        );
    }

    public function withStatus(UserStatus $status): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->email,
            $this->phone,
            $this->employeeNo,
            $this->department,
            $this->role,
            $status,
            $this->avatar,
            $this->mustChangePassword,
            $this->passwordHash,
        );
    }
}
