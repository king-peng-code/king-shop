<?php

namespace App\Http\Resources\Catalog;

use App\Application\Catalog\Services\ProductImageResolver;
use App\Domain\Catalog\Entities\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $resolver = app(ProductImageResolver::class);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'image_url' => $resolver->resolveUrl($product->imagePath, $product->uploadId),
            'category_id' => $product->categoryId,
            'category_name' => $product->categoryName,
            'status' => $product->status->value,
        ];
    }
}
