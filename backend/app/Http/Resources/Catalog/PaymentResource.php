<?php

namespace App\Http\Resources\Catalog;

use App\Domain\Payment\Entities\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'order_id' => $payment->orderId,
            'out_trade_no' => $payment->outTradeNo,
            'trade_no' => $payment->tradeNo,
            'amount' => $payment->amount,
            'channel' => $payment->channel->value,
            'status' => $payment->status->value,
            'paid_at' => $payment->paidAt?->format(DATE_ATOM),
        ];
    }
}
