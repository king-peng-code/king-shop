<?php

declare(strict_types=1);

namespace App\Application\Statistics\GetProxyPayerStats;

use App\Application\Statistics\DTO\ProxyPayerStatsDto;
use App\Domain\Statistics\Repositories\StatsRepositoryInterface;

class GetProxyPayerStatsHandler
{
    public function __construct(
        private readonly StatsRepositoryInterface $repository,
    ) {}

    public function handle(): ProxyPayerStatsDto
    {
        return new ProxyPayerStatsDto($this->repository->getProxyPayerStats());
    }
}
