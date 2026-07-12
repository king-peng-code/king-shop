<?php

namespace App\Http\Controllers\Admin;

use App\Application\Dashboard\GetDashboardStats\GetDashboardStatsHandler;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(GetDashboardStatsHandler $handler): JsonResponse
    {
        return ApiResponse::success($handler->handle()->toArray());
    }
}
