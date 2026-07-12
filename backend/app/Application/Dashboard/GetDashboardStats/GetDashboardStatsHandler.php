<?php

namespace App\Application\Dashboard\GetDashboardStats;

use App\Application\Dashboard\DTO\DashboardStatsDto;
use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;

class GetDashboardStatsHandler
{
    public function __construct(
        private readonly DashboardStatsRepositoryInterface $repository,
    ) {}

    public function handle(): DashboardStatsDto
    {
        return new DashboardStatsDto($this->repository->getStats());
    }
}
