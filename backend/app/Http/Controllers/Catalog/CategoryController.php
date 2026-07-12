<?php

namespace App\Http\Controllers\Catalog;

use App\Application\Catalog\ListVisibleCategories\ListVisibleCategoriesHandler;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\CategoryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(ListVisibleCategoriesHandler $handler): JsonResponse
    {
        $result = $handler->handle();

        return ApiResponse::success([
            'items' => CategoryResource::collection($result['items']),
        ]);
    }
}
