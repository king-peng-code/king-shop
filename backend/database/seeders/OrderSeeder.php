<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (OrderModel::query()->exists()) {
            $this->command?->info('Orders already exist, skipping seed.');

            return;
        }

        $user = UserModel::query()->where('phone', '13800000001')->first()
            ?? UserModel::factory()->create(['name' => '张三', 'phone' => '13800000001']);
        $product = ProductModel::query()->where('name', '拿铁')->first()
            ?? ProductModel::factory()->onSale()->create([
                'name' => '拿铁',
                'price' => 1500,
            ]);

        $this->seedOrder(
            user: $user,
            product: $product,
            orderNo: 'KS20260712000001',
            status: 'pending_payment',
            paidByExternalUserId: null,
            paidAt: null,
            cancelledAt: null,
            cancelReason: null,
        );

        $this->seedOrder(
            user: $user,
            product: $product,
            orderNo: 'KS20260712000002',
            status: 'paid',
            paidByExternalUserId: null,
            paidAt: now(),
            cancelledAt: null,
            cancelReason: null,
        );

        $this->seedOrder(
            user: $user,
            product: $product,
            orderNo: 'KS20260712000003',
            status: 'cancelled',
            paidByExternalUserId: null,
            paidAt: null,
            cancelledAt: now(),
            cancelReason: '超时未支付自动取消',
        );

        $payer = ExternalUserModel::factory()->create([
            'provider' => 'fake',
            'external_id' => 'seed-proxy-payer',
            'name' => '李四',
            'phone' => '13800000002',
        ]);

        $proxyOrder = OrderModel::factory()
            ->for($user, 'user')
            ->paid()
            ->proxy()
            ->create([
                'order_no' => 'KS20260712999999',
                'paid_by_external_user_id' => $payer->id,
                'total_amount' => 4500,
            ]);

        OrderItemModel::factory()->for($proxyOrder, 'order')->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->image_path,
            'price' => $product->price,
            'quantity' => 3,
            'subtotal' => $product->price * 3,
        ]);
    }

    private function seedOrder(
        UserModel $user,
        ProductModel $product,
        string $orderNo,
        string $status,
        ?int $paidByExternalUserId,
        $paidAt,
        $cancelledAt,
        ?string $cancelReason,
    ): void {
        $factory = OrderModel::factory()->for($user, 'user');

        if ($status === 'paid') {
            $factory = $factory->paid();
        } elseif ($status === 'cancelled') {
            $factory = $factory->cancelled();
        }

        $order = $factory->create([
            'order_no' => $orderNo,
            'total_amount' => 3000,
            'paid_by_external_user_id' => $paidByExternalUserId,
            'paid_at' => $paidAt,
            'cancelled_at' => $cancelledAt,
            'cancel_reason' => $cancelReason,
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
}
