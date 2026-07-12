<?php

namespace App\Application\Identity\Login;

use App\Application\Identity\DTO\LoginResultDto;
use App\Domain\Identity\Exceptions\AccountDisabledException;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Hash;

class LoginHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function handle(string $phone, string $password): LoginResultDto
    {
        $user = $this->repository->findByPhone($phone);

        if ($user === null || ! Hash::check($password, $user->passwordHash)) {
            throw new InvalidCredentialsException;
        }

        if (! $user->canLogin()) {
            throw new AccountDisabledException;
        }

        $model = UserModel::query()->findOrFail($user->id);
        $token = $model->createToken('api')->plainTextToken;

        return new LoginResultDto(
            token: $token,
            user: $user,
            mustChangePassword: $user->mustChangePassword,
        );
    }
}
