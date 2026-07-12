<?php

namespace App\Http\Resources\Admin;

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
            'user' => [
                'id' => $order->userId,
                'name' => $order->userName,
                'phone' => $order->userPhone,
                'department' => $order->userDepartment,
            ],
            'total_amount' => $order->totalAmount,
            'status' => $order->status->value,
            'payment_method' => $order->paymentMethod->value,
            'paid_by_user' => $order->paidByUserId !== null ? [
                'id' => $order->paidByUserId,
                'name' => $order->paidByUserName,
                'phone' => $order->paidByUserPhone,
                'department' => $order->paidByUserDepartment,
            ] : null,
            'paid_at' => $order->paidAt?->format(DATE_ATOM),
            'remark' => $order->remark,
            'cancelled_at' => $order->cancelledAt?->format(DATE_ATOM),
            'cancel_reason' => $order->cancelReason,
            'created_at' => $order->createdAt->format(DATE_ATOM),
        ];

        if ($order->items !== []) {
            $data['items'] = OrderItemResource::collection($order->items);
        }

        return $data;
    }
}
