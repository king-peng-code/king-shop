<?php

namespace App\Domain\Catalog\ValueObjects;

final class CategoryStatus
{
    public const ACTIVE = 'active';

    public const DISABLED = 'disabled';

    private function __construct(public readonly string $value) {}

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function disabled(): self
    {
        return new self(self::DISABLED);
    }

    public static function fromString(string $value): self
    {
        if (! in_array($value, [self::ACTIVE, self::DISABLED], true)) {
            throw new \InvalidArgumentException("Invalid status: {$value}");
        }

        return new self($value);
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }
}
