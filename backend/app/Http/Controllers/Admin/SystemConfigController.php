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
    public function index(GetSystemConfigsHandler $handler): JsonResponse
    {
        return ApiResponse::success($handler->handle());
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

        return ApiResponse::success($handler->handle($items));
    }
}
