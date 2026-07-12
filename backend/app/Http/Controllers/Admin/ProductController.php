<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\CreateProduct\CreateProductHandler;
use App\Application\Catalog\DTO\CreateProductCommand;
use App\Application\Catalog\DTO\ProductListQuery;
use App\Application\Catalog\DTO\UpdateProductCommand;
use App\Application\Catalog\GetProduct\GetProductHandler;
use App\Application\Catalog\ListProducts\ListProductsHandler;
use App\Application\Catalog\UpdateProduct\UpdateProductHandler;
use App\Domain\Catalog\ValueObjects\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, ListProductsHandler $handler): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $categoryId = $request->query('category_id');
        $status = $request->query('status');

        $result = $handler->handle(
            new ProductListQuery(
                categoryId: $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null,
                status: $status !== null && $status !== '' ? (string) $status : null,
                keyword: (string) $request->query('keyword', ''),
                page: $page,
                perPage: $perPage,
            ),
        );

        return ApiResponse::success([
            'items' => ProductResource::collection($result['items']),
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(
        CreateProductRequest $request,
        CreateProductHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $product = $handler->handle(
            new CreateProductCommand(
                categoryId: $validated['category_id'],
                name: $validated['name'],
                description: $validated['description'] ?? null,
                price: $validated['price'],
                uploadId: $validated['upload_id'] ?? null,
                imagePath: $validated['image_path'] ?? null,
                status: ProductStatus::fromString($validated['status'] ?? ProductStatus::OFF_SALE),
                sort: (int) ($validated['sort'] ?? 0),
            ),
        );

        return ApiResponse::success(new ProductResource($product), 'ok', 201);
    }

    public function show(int $product, GetProductHandler $handler): JsonResponse
    {
        return ApiResponse::success(new ProductResource($handler->handle($product)));
    }

    public function update(
        UpdateProductRequest $request,
        int $product,
        UpdateProductHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $updated = $handler->handle(
            new UpdateProductCommand(
                productId: $product,
                categoryId: $validated['category_id'],
                name: $validated['name'],
                description: $validated['description'] ?? null,
                price: $validated['price'],
                uploadId: $validated['upload_id'] ?? null,
                imagePath: $validated['image_path'] ?? null,
                status: ProductStatus::fromString($validated['status']),
                sort: $validated['sort'],
            ),
        );

        return ApiResponse::success(new ProductResource($updated));
    }
}
