<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderModel>
 */
class OrderFactory extends Factory
{
    protected $model = OrderModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_no' => 'KS'.now()->format('YmdHis').fake()->unique()->numerify('###'),
            'user_id' => UserModel::factory(),
            'total_amount' => 3000,
            'status' => 'pending_payment',
            'payment_method' => 'self',
            'paid_by_user_id' => null,
            'paid_at' => null,
            'remark' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => '测试取消',
        ]);
    }

    public function proxy(): static
    {
        return $this->state(fn () => [
            'payment_method' => 'proxy',
        ]);
    }
}
