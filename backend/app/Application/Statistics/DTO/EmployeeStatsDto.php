<?php

declare(strict_types=1);

namespace App\Application\Statistics\DTO;

readonly class EmployeeStatsDto
{
    /**
     * @param list<array{
     *   user_id: int,
     *   name: string,
     *   phone: string,
     *   order_count: int,
     *   total_amount: int
     * }> $items
     */
    public function __construct(
        private array $items,
    ) {}

    /**
     * @return list<array{
     *   user_id: int,
     *   name: string,
     *   phone: string,
     *   order_count: int,
     *   total_amount: int
     * }>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
