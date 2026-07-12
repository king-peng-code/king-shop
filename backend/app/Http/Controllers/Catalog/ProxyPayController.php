<?php

namespace App\Http\Controllers\Catalog;

use App\Application\ProxyPay\GenerateProxyPayLink\GenerateProxyPayLinkHandler;
use App\Application\ProxyPay\GetProxyPayPreview\GetProxyPayPreviewHandler;
use App\Application\ProxyPay\InitiateProxyPayment\InitiateProxyPaymentHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\InitiateProxyPaymentRequest;
use App\Http\Resources\Catalog\PaymentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProxyPayController extends Controller
{
    public function show(string $token, GetProxyPayPreviewHandler $handler): JsonResponse
    {
        return ApiResponse::success($handler->handle($token));
    }

    public function pay(
        InitiateProxyPaymentRequest $request,
        string $token,
        InitiateProxyPaymentHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $handler->handle(
            token: $token,
            payerUserId: $request->user()->id,
            openid: $validated['openid'] ?? null,
            channel: $validated['channel'] ?? null,
        );

        return ApiResponse::success([
            'payment' => new PaymentResource($result['payment']),
            'pay_params' => $result['pay_params'],
        ]);
    }
}
