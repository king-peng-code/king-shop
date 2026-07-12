<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Statistics\GetEmployeeStats\GetEmployeeStatsHandler;
use App\Application\Statistics\GetProxyPayerStats\GetProxyPayerStatsHandler;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function employeeStats(GetEmployeeStatsHandler $handler): JsonResponse
    {
        return ApiResponse::success($handler->handle()->toArray());
    }

    public function proxyPayerStats(GetProxyPayerStatsHandler $handler): JsonResponse
    {
        return ApiResponse::success($handler->handle()->toArray());
    }
}
