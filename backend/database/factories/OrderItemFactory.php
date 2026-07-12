<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemModel>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItemModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = 1500;
        $quantity = 2;

        return [
            'order_id' => OrderModel::factory(),
            'product_id' => ProductModel::factory(),
            'product_name' => fake()->words(2, true),
            'product_image' => null,
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
        ];
    }
}
