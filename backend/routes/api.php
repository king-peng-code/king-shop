<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Admin\SystemConfigController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ApiResponse::success(['status' => 'healthy']));

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware(['auth:sanctum', 'password.changed', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('/configs', [SystemConfigController::class, 'index']);
    Route::put('/configs', [SystemConfigController::class, 'update']);
    Route::post('/upload', [UploadController::class, 'store']);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('categories', AdminCategoryController::class);
    Route::apiResource('products', AdminProductController::class)->except(['destroy']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/preparing', [OrderController::class, 'preparing']);
    Route::post('orders/{order}/ready', [OrderController::class, 'ready']);
    Route::post('orders/{order}/complete', [OrderController::class, 'complete']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'password.changed'])->group(function (): void {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
});
