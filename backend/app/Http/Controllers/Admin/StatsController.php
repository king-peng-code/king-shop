<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Statistics\GetEmployeeStats\GetEmployeeStatsHandler;
use App\Application\Statistics\GetProxyPayerStats\GetProxyPayerStatsHandler;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function employeeStats(Request $request, GetEmployeeStatsHandler $handler): JsonResponse
    {
        $keyword = $request->query('keyword');

        return ApiResponse::success($handler->handle($keyword !== null && $keyword !== '' ? (string) $keyword : null)->toArray());
    }

    public function proxyPayerStats(Request $request, GetProxyPayerStatsHandler $handler): JsonResponse
    {
        $keyword = $request->query('keyword');

        return ApiResponse::success($handler->handle($keyword !== null && $keyword !== '' ? (string) $keyword : null)->toArray());
    }
}
