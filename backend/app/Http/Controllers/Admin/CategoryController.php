<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\CreateCategory\CreateCategoryHandler;
use App\Application\Catalog\DeleteCategory\DeleteCategoryHandler;
use App\Application\Catalog\DTO\CreateCategoryCommand;
use App\Application\Catalog\DTO\UpdateCategoryCommand;
use App\Application\Catalog\GetCategory\GetCategoryHandler;
use App\Application\Catalog\ListCategories\ListCategoriesHandler;
use App\Application\Catalog\UpdateCategory\UpdateCategoryHandler;
use App\Domain\Catalog\ValueObjects\CategoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(ListCategoriesHandler $handler): JsonResponse
    {
        $result = $handler->handle();

        return ApiResponse::success([
            'items' => CategoryResource::collection($result['items']),
        ]);
    }

    public function store(
        CreateCategoryRequest $request,
        CreateCategoryHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $category = $handler->handle(
            new CreateCategoryCommand(
                name: $validated['name'],
                sort: (int) ($validated['sort'] ?? 0),
                status: CategoryStatus::fromString($validated['status'] ?? CategoryStatus::ACTIVE),
            ),
        );

        return ApiResponse::success(new CategoryResource($category), 'ok', 201);
    }

    public function show(int $category, GetCategoryHandler $handler): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($handler->handle($category)));
    }

    public function update(
        UpdateCategoryRequest $request,
        int $category,
        UpdateCategoryHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $updated = $handler->handle(
            new UpdateCategoryCommand(
                categoryId: $category,
                name: $validated['name'],
                sort: $validated['sort'],
                status: CategoryStatus::fromString($validated['status']),
            ),
        );

        return ApiResponse::success(new CategoryResource($updated));
    }

    public function destroy(int $category, DeleteCategoryHandler $handler): JsonResponse
    {
        $handler->handle($category);

        return ApiResponse::success();
    }
}
