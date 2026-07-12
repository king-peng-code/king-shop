<?php

namespace App\Domain\Identity\ValueObjects;

final class Role
{
    public const EMPLOYEE = 'employee';

    public const ADMIN = 'admin';

    public const SUPER_ADMIN = 'super_admin';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! in_array($value, [self::EMPLOYEE, self::ADMIN, self::SUPER_ADMIN], true)) {
            throw new \InvalidArgumentException("Invalid role: {$value}");
        }

        return new self($value);
    }

    public static function employee(): self
    {
        return new self(self::EMPLOYEE);
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public static function superAdmin(): self
    {
        return new self(self::SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return in_array($this->value, [self::ADMIN, self::SUPER_ADMIN], true);
    }

    public function canAssignRole(self $target): bool
    {
        if ($this->value === self::SUPER_ADMIN) {
            return true;
        }

        return $target->value === self::EMPLOYEE;
    }
}
