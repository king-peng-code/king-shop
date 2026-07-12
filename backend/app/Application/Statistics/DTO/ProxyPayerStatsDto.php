<?php

declare(strict_types=1);

namespace App\Application\Statistics\DTO;

readonly class ProxyPayerStatsDto
{
    /**
     * @param list<array{
     *   external_user_id: int,
     *   name: string|null,
     *   phone: string|null,
     *   provider: string,
     *   order_count: int,
     *   total_amount: int
     * }> $items
     */
    public function __construct(
        private array $items,
    ) {}

    /**
     * @return list<array{
     *   external_user_id: int,
     *   name: string|null,
     *   phone: string|null,
     *   provider: string,
     *   order_count: int,
     *   total_amount: int
     * }>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
