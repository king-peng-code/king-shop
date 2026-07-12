<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\OrderController as CatalogOrderController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProxyPayController;
use App\Http\Controllers\PaymentNotifyController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\SystemConfigController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ApiResponse::success(['status' => 'healthy']));

Route::post('/payments/notify/alipay', [PaymentNotifyController::class, 'alipay']);
Route::post('/payments/notify/wechat', [PaymentNotifyController::class, 'wechat']);

Route::get('/proxy-pay/{token}', [ProxyPayController::class, 'show']);
Route::post('/proxy-pay/{token}/pay', [ProxyPayController::class, 'pay']);

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
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('stats/employees', [StatsController::class, 'employeeStats']);
    Route::get('stats/proxy-payers', [StatsController::class, 'proxyPayerStats']);
    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'password.changed'])->group(function (): void {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::post('/orders', [CatalogOrderController::class, 'store']);
    Route::get('/orders', [CatalogOrderController::class, 'index']);
    Route::get('/orders/{order}', [CatalogOrderController::class, 'show']);
    Route::post('/orders/{order}/pay', [CatalogOrderController::class, 'pay']);
    Route::post('/orders/{order}/proxy-pay-link', [CatalogOrderController::class, 'proxyPayLink']);
    Route::post('/orders/{order}/cancel', [CatalogOrderController::class, 'cancel']);
});
