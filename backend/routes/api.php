<?php

use App\Http\Controllers\Admin\EmployeeController;
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
});
