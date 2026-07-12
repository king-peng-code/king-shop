<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryModel>
 */
class CategoryFactory extends Factory
{
    protected $model = CategoryModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'sort' => 0,
            'status' => 'active',
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}
