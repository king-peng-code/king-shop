<?php

namespace App\Http\Controllers\Catalog;

use App\Application\Catalog\GetVisibleProduct\GetVisibleProductHandler;
use App\Application\Catalog\ListVisibleProducts\ListVisibleProductsHandler;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\ProductResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, ListVisibleProductsHandler $handler): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $categoryId = $request->query('category_id');

        $result = $handler->handle(
            categoryId: $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null,
            page: $page,
            perPage: $perPage,
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

    public function show(int $product, GetVisibleProductHandler $handler): JsonResponse
    {
        return ApiResponse::success(new ProductResource($handler->handle($product)));
    }
}
