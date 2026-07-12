<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customer = UserModel::factory()->create(['name' => '张三']);
        $payer = UserModel::factory()->create(['name' => '李四']);
        $products = ProductModel::factory()->onSale()->count(4)->create();

        $orders = [
            OrderModel::factory()->for($customer, 'user')->create(),
            OrderModel::factory()->for($customer, 'user')->proxy()->create([
                'paid_by_user_id' => $payer->id,
            ]),
            OrderModel::factory()->for($customer, 'user')->preparing()->create(),
            OrderModel::factory()->for($customer, 'user')->ready()->create(),
            OrderModel::factory()->for($customer, 'user')->completed()->create(),
            OrderModel::factory()->for($customer, 'user')->cancelled()->create(),
        ];

        foreach ($orders as $order) {
            $this->seedItems($order, $products);
        }
    }

    /**
     * @param  Collection<int, ProductModel>  $products
     */
    private function seedItems(OrderModel $order, Collection $products): void
    {
        $selected = $products->random(min(2, $products->count()));
        $totalAmount = 0;

        foreach ($selected as $product) {
            $quantity = fake()->numberBetween(1, 3);
            $subtotal = $product->price * $quantity;

            OrderItemModel::factory()->for($order, 'order')->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_image' => $product->image_path,
                'price' => $product->price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ]);

            $totalAmount += $subtotal;
        }

        $order->update(['total_amount' => $totalAmount]);
    }
}
