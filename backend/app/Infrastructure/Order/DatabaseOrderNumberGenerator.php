<?php

namespace App\Infrastructure\Order;

use App\Domain\Order\Services\OrderNumberGeneratorInterface;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use Illuminate\Support\Carbon;

class DatabaseOrderNumberGenerator implements OrderNumberGeneratorInterface
{
    public function generate(): string
    {
        $prefix = 'KS'.Carbon::now()->format('YmdHi');

        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $sequence = str_pad((string) ($attempt + 1), 3, '0', STR_PAD_LEFT);
            $orderNo = $prefix.$sequence;

            if (! OrderModel::query()->where('order_no', $orderNo)->exists()) {
                return $orderNo;
            }
        }

        throw new \RuntimeException('Failed to generate unique order number.');
    }
}
