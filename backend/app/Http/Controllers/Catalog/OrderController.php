<?php

namespace App\Http\Controllers\Catalog;

use App\Application\Order\CancelMyOrder\CancelMyOrderHandler;
use App\Application\Order\CompleteMyOrder\CompleteMyOrderHandler;
use App\Application\Order\CreateOrder\CreateOrderHandler;
use App\Application\Order\DTO\CreateOrderCommand;
use App\Application\Order\DTO\CreateOrderItemCommand;
use App\Application\Order\DTO\UserOrderListQuery;
use App\Application\Order\GetMyOrder\GetMyOrderHandler;
use App\Application\Order\ListMyOrders\ListMyOrdersHandler;
use App\Application\Payment\InitiatePayment\InitiatePaymentHandler;
use App\Application\ProxyPay\GenerateProxyPayLink\GenerateProxyPayLinkHandler;
use App\Domain\Order\ValueObjects\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateOrderRequest;
use App\Http\Requests\Catalog\InitiatePaymentRequest;
use App\Http\Resources\Catalog\OrderResource;
use App\Http\Resources\Catalog\PaymentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(
        CreateOrderRequest $request,
        CreateOrderHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $order = $handler->handle(
            new CreateOrderCommand(
                userId: $user->id,
                items: array_map(
                    fn (array $item) => new CreateOrderItemCommand(
                        productId: $item['product_id'],
                        quantity: $item['quantity'],
                    ),
                    $validated['items'],
                ),
                paymentMethod: PaymentMethod::fromString($validated['payment_method'] ?? PaymentMethod::SELF),
                remark: $validated['remark'] ?? null,
            ),
        );

        return ApiResponse::success(new OrderResource($order), 'ok', 201);
    }

    public function index(Request $request, ListMyOrdersHandler $handler): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $status = $request->query('status');

        $result = $handler->handle(
            new UserOrderListQuery(
                userId: $request->user()->id,
                status: $status !== null && $status !== '' ? (string) $status : null,
                page: $page,
                perPage: $perPage,
            ),
        );

        return ApiResponse::success([
            'items' => OrderResource::collection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    public function show(Request $request, int $order, GetMyOrderHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($handler->handle($order, $request->user()->id)),
        );
    }

    public function cancel(Request $request, int $order, CancelMyOrderHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($handler->handle($order, $request->user()->id)),
        );
    }

    public function complete(
        Request $request,
        int $order,
        CompleteMyOrderHandler $handler,
    ): JsonResponse {
        return ApiResponse::success(
            new OrderResource($handler->handle($order, $request->user()->id)),
        );
    }

    public function pay(
        InitiatePaymentRequest $request,
        int $order,
        InitiatePaymentHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();
        $result = $handler->handle(
            orderId: $order,
            userId: $request->user()->id,
            channel: $validated['channel'] ?? null,
        );

        return ApiResponse::success([
            'payment' => new PaymentResource($result['payment']),
            'pay_params' => $result['pay_params'],
        ]);
    }

    public function proxyPayLink(
        Request $request,
        int $order,
        GenerateProxyPayLinkHandler $handler,
    ): JsonResponse {
        return ApiResponse::success($handler->handle($order, $request->user()->id));
    }
}
