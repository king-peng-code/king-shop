<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Domain\SystemConfig\Services\ConfigEncryptionInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;

class EloquentSystemConfigRepository implements SystemConfigRepositoryInterface
{
    public function __construct(
        private readonly ConfigEncryptionInterface $encryption,
    ) {}

    public function all(): array
    {
        return SystemConfigModel::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn (SystemConfigModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByGroupAndKey(string $group, string $key): ?SystemConfig
    {
        $model = SystemConfigModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function updateValue(string $group, string $key, string $plainValue): void
    {
        $model = SystemConfigModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if ($model === null) {
            return;
        }

        SystemConfigModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->update(['value' => $this->encodeValue($plainValue, $model->is_sensitive)]);
    }

    public function exists(string $group, string $key): bool
    {
        return SystemConfigModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->exists();
    }

    private function toEntity(SystemConfigModel $model): SystemConfig
    {
        return new SystemConfig(
            group: $model->group,
            key: $model->key,
            value: $this->decodeValue($model->value, $model->is_sensitive),
            isSensitive: $model->is_sensitive,
            description: $model->description,
        );
    }

    private function encodeValue(string $plainValue, bool $isSensitive): string
    {
        if (! $isSensitive) {
            return $plainValue;
        }

        return $this->encryption->encrypt($plainValue);
    }

    private function decodeValue(string $storedValue, bool $isSensitive): string
    {
        if (! $isSensitive) {
            return $storedValue;
        }

        return $this->encryption->decrypt($storedValue);
    }
}
