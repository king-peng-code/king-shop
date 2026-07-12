<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\ProxyPay\Entities\ProxyPayToken;
use App\Domain\ProxyPay\Repositories\ProxyPayTokenRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ProxyPayTokenModel;

class EloquentProxyPayTokenRepository implements ProxyPayTokenRepositoryInterface
{
    public function findByToken(string $token): ?ProxyPayToken
    {
        $model = ProxyPayTokenModel::query()->where('token', $token)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findActiveByOrderId(int $orderId): ?ProxyPayToken
    {
        $model = ProxyPayTokenModel::query()
            ->where('order_id', $orderId)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(ProxyPayToken $token): ProxyPayToken
    {
        $attributes = [
            'order_id' => $token->orderId,
            'token' => $token->token,
            'expires_at' => $token->expiresAt->format('Y-m-d H:i:s'),
        ];

        if ($token->id === null) {
            $model = ProxyPayTokenModel::query()->create($attributes);
        } else {
            $model = ProxyPayTokenModel::query()->findOrFail($token->id);
            $model->fill($attributes);
            $model->save();
        }

        return $this->toDomain($model->fresh());
    }

    private function toDomain(ProxyPayTokenModel $model): ProxyPayToken
    {
        return new ProxyPayToken(
            id: $model->id,
            orderId: $model->order_id,
            token: $model->token,
            expiresAt: \DateTimeImmutable::createFromMutable($model->expires_at),
            createdAt: \DateTimeImmutable::createFromMutable($model->created_at),
        );
    }
}
