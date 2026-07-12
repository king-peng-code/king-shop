<?php

namespace App\Domain\SystemConfig\Entities;

final class SystemConfig
{
    public const MASK_PLACEHOLDER = '****';

    /** @var array<string, list<string>> */
    public const READONLY_KEYS = [];

    public function __construct(
        public readonly string $group,
        public readonly string $key,
        public readonly string $value,
        public readonly bool $isSensitive,
        public readonly ?string $description = null,
    ) {}

    public function displayValue(): string
    {
        if ($this->isSensitive && $this->value !== '') {
            return self::MASK_PLACEHOLDER;
        }

        return $this->value;
    }

    public static function isReadonly(string $group, string $key): bool
    {
        return in_array($key, self::READONLY_KEYS[$group] ?? [], true);
    }
}
