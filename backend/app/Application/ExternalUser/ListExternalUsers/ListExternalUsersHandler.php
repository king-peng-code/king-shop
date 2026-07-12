<?php

declare(strict_types=1);

namespace App\Application\ExternalUser\ListExternalUsers;

use App\Domain\ExternalUser\Repositories\ExternalUserRepositoryInterface;

class ListExternalUsersHandler
{
    public function __construct(
        private readonly ExternalUserRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: \App\Domain\ExternalUser\Entities\ExternalUser[], meta: array{total: int, page: int, per_page: int}}
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
