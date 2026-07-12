<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Order\CancelOrder\CancelOrderHandler;
use App\Application\Order\DTO\AdminOrderListQuery;
use App\Application\Order\GetAdminOrder\GetAdminOrderHandler;
use App\Application\Order\ListAdminOrders\ListAdminOrdersHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request, ListAdminOrdersHandler $handler): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $userId = $request->query('user_id');
        $paidByExternalUserId = $request->query('paid_by_external_user_id');

        $result = $handler->handle(
            new AdminOrderListQuery(
                status: $request->query('status') !== null && $request->query('status') !== ''
                    ? (string) $request->query('status')
                    : null,
                userId: $userId !== null && $userId !== '' ? (int) $userId : null,
                dateFrom: $request->query('date_from') !== null && $request->query('date_from') !== ''
                    ? (string) $request->query('date_from')
                    : null,
                dateTo: $request->query('date_to') !== null && $request->query('date_to') !== ''
                    ? (string) $request->query('date_to')
                    : null,
                keyword: (string) $request->query('keyword', ''),
                page: $page,
                perPage: $perPage,
                paidByExternalUserId: $paidByExternalUserId !== null && $paidByExternalUserId !== ''
                    ? (int) $paidByExternalUserId
                    : null,
            ),
        );

        return ApiResponse::success([
            'items' => OrderResource::collection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    public function show(int $order, GetAdminOrderHandler $handler): JsonResponse
    {
        return ApiResponse::success(new OrderResource($handler->handle($order)));
    }

    public function cancel(
        CancelOrderRequest $request,
        int $order,
        CancelOrderHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        return ApiResponse::success(new OrderResource(
            $handler->handle($order, $validated['cancel_reason'] ?? null),
        ));
    }
}
