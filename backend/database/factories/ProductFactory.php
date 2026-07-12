<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductModel>
 */
class ProductFactory extends Factory
{
    protected $model = ProductModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => CategoryModel::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price' => 1500,
            'upload_id' => null,
            'image_path' => null,
            'status' => 'off_sale',
            'sort' => 0,
        ];
    }

    public function onSale(): static
    {
        return $this->state(['status' => 'on_sale']);
    }

    public function offSale(): static
    {
        return $this->state(['status' => 'off_sale']);
    }
}
