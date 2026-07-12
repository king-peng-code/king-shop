<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentModel>
 */
class PaymentFactory extends Factory
{
    protected $model = PaymentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderModel::factory(),
            'out_trade_no' => 'PAY'.now()->format('YmdHis').fake()->unique()->numerify('###'),
            'trade_no' => null,
            'amount' => 3000,
            'channel' => 'fake',
            'status' => 'pending',
            'paid_at' => null,
            'raw_notify' => null,
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => 'success',
            'trade_no' => 'FAKE'.fake()->numerify('########'),
            'paid_at' => now(),
        ]);
    }
}
