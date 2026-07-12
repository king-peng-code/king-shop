<?php

namespace App\Infrastructure\Payment;

use Illuminate\Support\Str;

class OutTradeNoGenerator
{
    public function generate(int $orderId): string
    {
        return 'PAY'.$orderId.now()->format('YmdHis').Str::upper(Str::random(4));
    }
}
