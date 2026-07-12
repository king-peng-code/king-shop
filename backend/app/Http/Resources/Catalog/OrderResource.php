<?php

namespace App\Http\Resources\Catalog;

use App\Domain\Order\Entities\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        $data = [
            'id' => $order->id,
            'order_no' => $order->orderNo,
            'total_amount' => $order->totalAmount,
            'status' => $order->status->value,
            'payment_method' => $order->paymentMethod->value,
            'paid_at' => $order->paidAt?->format(DATE_ATOM),
            'remark' => $order->remark,
            'cancelled_at' => $order->cancelledAt?->format(DATE_ATOM),
            'cancel_reason' => $order->cancelReason,
            'created_at' => $order->createdAt->format(DATE_ATOM),
        ];

        if ($order->items !== []) {
            $data['items'] = OrderItemResource::collection($order->items);
        }

        if ($order->paidByExternalUserId !== null) {
            $data['paid_by_payer'] = [
                'id' => $order->paidByExternalUserId,
                'name' => $order->paidByPayerName,
                'phone' => $order->paidByPayerPhone,
                'provider' => $order->paidByPayerProvider ?? null,
            ];
        }

        return $data;
    }
}
