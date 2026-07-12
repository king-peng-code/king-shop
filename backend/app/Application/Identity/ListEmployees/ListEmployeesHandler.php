<?php

namespace App\Application\Identity\ListEmployees;

use App\Domain\Identity\Repositories\UserRepositoryInterface;

class ListEmployeesHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\Identity\Entities\User[], meta: array{total: int, page: int, per_page: int}}
     */
    public function handle(string $keyword, int $page, int $perPage): array
    {
        $result = $this->repository->search($keyword, $page, $perPage);

        return [
            'items' => $result['items'],
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }
}
