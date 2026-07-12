<?php

namespace App\Domain\SystemConfig\Entities;

final class SystemConfig
{
    public const MASK_PLACEHOLDER = '****';

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
}
