<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\ExternalUser\Entities\ExternalUser;
use App\Domain\ExternalUser\Repositories\ExternalUserRepositoryInterface;
use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;
use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;

class EloquentExternalUserRepository implements ExternalUserRepositoryInterface
{
    public function findById(int $id): ?ExternalUser
    {
        $model = ExternalUserModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByProviderAndExternalId(string $provider, string $externalId): ?ExternalUser
    {
        $model = ExternalUserModel::query()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function search(string $keyword, int $page, int $perPage): array
    {
        $query = ExternalUserModel::query()->orderByDesc('id');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (ExternalUserModel $model) => $this->toDomain($model))
                ->all(),
            'total' => $paginator->total(),
        ];
    }

    public function save(ExternalUser $user): ExternalUser
    {
        $attributes = [
            'provider' => $user->provider->value,
            'external_id' => $user->externalId,
            'name' => $user->name,
            'phone' => $user->phone,
            'tags' => $user->tags,
        ];

        if ($user->id === null) {
            $model = ExternalUserModel::query()->create($attributes);
        } else {
            $model = ExternalUserModel::query()->findOrFail($user->id);
            $model->fill($attributes);
            $model->save();
        }

        return $this->toDomain($model->fresh());
    }

    private function toDomain(ExternalUserModel $model): ExternalUser
    {
        return new ExternalUser(
            id: $model->id,
            provider: ExternalUserProvider::fromString($model->provider),
            externalId: $model->external_id,
            name: $model->name,
            phone: $model->phone,
            tags: $model->tags ?? [],
            createdAt: \DateTimeImmutable::createFromMutable($model->created_at),
            updatedAt: \DateTimeImmutable::createFromMutable($model->updated_at),
        );
    }
}
