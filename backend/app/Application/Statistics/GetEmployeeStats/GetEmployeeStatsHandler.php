<?php

declare(strict_types=1);

namespace App\Application\Statistics\GetEmployeeStats;

use App\Application\Statistics\DTO\EmployeeStatsDto;
use App\Domain\Statistics\Repositories\StatsRepositoryInterface;

class GetEmployeeStatsHandler
{
    public function __construct(
        private readonly StatsRepositoryInterface $repository,
    ) {}

    public function handle(?string $keyword = null): EmployeeStatsDto
    {
        return new EmployeeStatsDto($this->repository->getEmployeeStats($keyword));
    }
}
