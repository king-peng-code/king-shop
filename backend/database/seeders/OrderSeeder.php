<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $user = UserModel::factory()->create(['name' => '张三', 'phone' => '13800000001']);
        $payer = UserModel::factory()->create(['name' => '李四', 'phone' => '13800000002']);
        $product = ProductModel::factory()->onSale()->create([
            'name' => '拿铁',
            'price' => 1500,
        ]);

        $statuses = [
            'pending_payment' => null,
            'paid' => 'paid',
            'preparing' => 'preparing',
            'ready' => 'ready',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ];

        foreach ($statuses as $status => $factoryState) {
            $factory = OrderModel::factory()->for($user, 'user');

            if ($factoryState !== null) {
                $factory = $factory->{$factoryState}();
            }

            if ($status === 'cancelled') {
                $factory = $factory->cancelled();
            }

            $order = $factory->create([
                'order_no' => 'KS20260712'.str_pad((string) array_search($status, array_keys($statuses), true), 6, '0', STR_PAD_LEFT),
                'total_amount' => 3000,
            ]);

            OrderItemModel::factory()->for($order, 'order')->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_image' => $product->image_path,
                'price' => $product->price,
                'quantity' => 2,
                'subtotal' => $product->price * 2,
            ]);
        }

        $proxyOrder = OrderModel::factory()
            ->for($user, 'user')
            ->paid()
            ->proxy()
            ->create([
                'order_no' => 'KS20260712999999',
                'paid_by_user_id' => $payer->id,
                'total_amount' => 4500,
            ]);

        OrderItemModel::factory()->for($proxyOrder)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->image_path,
            'price' => $product->price,
            'quantity' => 3,
            'subtotal' => $product->price * 3,
        ]);
    }
}
