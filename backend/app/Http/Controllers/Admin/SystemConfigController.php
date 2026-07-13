<?php

namespace App\Http\Controllers\Admin;

use App\Application\SystemConfig\DTO\SystemConfigItemDto;
use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Application\SystemConfig\UpdateSystemConfigs\UpdateSystemConfigsHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemConfigsRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class SystemConfigController extends Controller
{
    public function index(
        GetSystemConfigsHandler $handler,
        \Illuminate\Http\Request $request,
    ): JsonResponse {
        $isSuperAdmin = ($request->user()->role ?? '') === 'super_admin';

        return ApiResponse::success($handler->handle(exposeSensitive: $isSuperAdmin));
    }

    public function update(
        UpdateSystemConfigsRequest $request,
        UpdateSystemConfigsHandler $handler,
    ): JsonResponse {
        $items = array_map(
            fn (array $config) => new SystemConfigItemDto(
                $config['group'],
                $config['key'],
                $config['value'],
            ),
            $request->validated('configs'),
        );

        return ApiResponse::success($handler->handle($items, $request->user()->role));
    }
}
