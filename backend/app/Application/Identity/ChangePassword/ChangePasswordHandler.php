<?php

namespace App\Application\Identity\ChangePassword;

use App\Domain\Identity\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function handle(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->repository->findById($userId);

        if ($user === null || ! Hash::check($currentPassword, $user->passwordHash)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码错误'],
            ]);
        }

        $updated = $user->withPassword(Hash::make($newPassword), false);
        $this->repository->save($updated);
    }
}
