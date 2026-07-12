<?php

namespace App\Application\Dashboard\GetDashboardStats;

use App\Application\Dashboard\DTO\DashboardStatsDto;
use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class GetDashboardStatsHandler
{
    public function __construct(
        private readonly DashboardStatsRepositoryInterface $repository,
    ) {}

    public function handle(): DashboardStatsDto
    {
        $data = Cache::remember('dashboard:stats', 120, fn (): array => $this->repository->getStats());

        return new DashboardStatsDto($data);
    }
}
