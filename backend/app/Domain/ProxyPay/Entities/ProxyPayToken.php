<?php

namespace App\Domain\ProxyPay\Entities;

final class ProxyPayToken
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $orderId,
        public readonly string $token,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable;

        return $now >= $this->expiresAt;
    }
}
