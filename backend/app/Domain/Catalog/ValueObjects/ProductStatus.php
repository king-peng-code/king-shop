<?php

namespace App\Domain\Catalog\ValueObjects;

final class ProductStatus
{
    public const ON_SALE = 'on_sale';

    public const OFF_SALE = 'off_sale';

    private function __construct(public readonly string $value) {}

    public static function onSale(): self
    {
        return new self(self::ON_SALE);
    }

    public static function offSale(): self
    {
        return new self(self::OFF_SALE);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isOnSale(): bool
    {
        return $this->value === self::ON_SALE;
    }
}
