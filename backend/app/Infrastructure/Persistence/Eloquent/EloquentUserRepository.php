<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Identity\ValueObjects\Role;
use App\Domain\Identity\ValueObjects\UserStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $model = UserModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByPhone(string $phone): ?User
    {
        $model = UserModel::query()->where('phone', $phone)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(User $user): User
    {
        $attributes = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'avatar' => $user->avatar,
            'must_change_password' => $user->mustChangePassword,
            'password' => $user->passwordHash,
        ];

        if ($user->id === null) {
            $model = UserModel::query()->create($attributes);
        } else {
            $model = UserModel::query()->findOrFail($user->id);
            $model->fill($attributes);
            $model->save();
        }

        return $this->toDomain($model->fresh());
    }

    public function search(string $keyword, int $page, int $perPage): array
    {
        $query = UserModel::query()->orderByDesc('id');

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (UserModel $model) => $this->toDomain($model))
                ->all(),
            'total' => $paginator->total(),
        ];
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            phone: $model->phone,
            role: Role::fromString($model->role),
            status: UserStatus::fromString($model->status),
            avatar: $model->avatar,
            mustChangePassword: (bool) $model->must_change_password,
            passwordHash: $model->password,
        );
    }
}
