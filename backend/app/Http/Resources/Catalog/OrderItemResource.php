<?php

namespace App\Http\Resources\Catalog;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Entities\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'product_id' => $item->productId,
            'product_name' => $item->productName,
            'product_image' => $item->productImage,
            'price' => $item->price,
            'quantity' => $item->quantity,
            'subtotal' => $item->subtotal,
        ];
    }
}
