<?php

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByPhone(string $phone): ?User;

    public function save(User $user): User;

    /**
     * @return array{items: User[], total: int}
     */
    public function search(string $keyword, int $page, int $perPage): array;
}
