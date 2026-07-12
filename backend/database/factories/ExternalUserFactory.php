<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExternalUserModel>
 */
class ExternalUserFactory extends Factory
{
    protected $model = ExternalUserModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'fake',
            'external_id' => 'fake-'.Str::random(16),
            'name' => fake()->name(),
            'phone' => null,
            'tags' => [],
        ];
    }
}
