<?php

declare(strict_types=1);

namespace App\Domain\Statistics\Repositories;

interface StatsRepositoryInterface
{
    /**
     * @return list<array{
     *   user_id: int,
     *   name: string,
     *   phone: string,
     *   order_count: int,
     *   total_amount: int
     * }>
     */
    public function getEmployeeStats(?string $keyword = null): array;

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
    public function getProxyPayerStats(?string $keyword = null): array;
}
