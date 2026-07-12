<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProxyPayTokenModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProxyPayTokenModel>
 */
class ProxyPayTokenFactory extends Factory
{
    protected $model = ProxyPayTokenModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderModel::factory()->proxy(),
            'token' => Str::random(48),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
