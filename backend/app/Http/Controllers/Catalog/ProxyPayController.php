<?php

namespace App\Http\Controllers\Catalog;

use App\Application\ExternalUser\UpsertExternalUser\UpsertExternalUserHandler;
use App\Application\ProxyPay\GetProxyPayPreview\GetProxyPayPreviewHandler;
use App\Application\ProxyPay\InitiateProxyPayment\InitiateProxyPaymentHandler;
use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\InitiateProxyPaymentRequest;
use App\Http\Resources\Catalog\PaymentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProxyPayController extends Controller
{
    public function show(string $token, GetProxyPayPreviewHandler $handler): JsonResponse
    {
        return ApiResponse::success($handler->handle($token));
    }

    public function pay(
        InitiateProxyPaymentRequest $request,
        string $token,
        UpsertExternalUserHandler $upsertHandler,
        InitiateProxyPaymentHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $provider = ExternalUserProvider::fromString($validated['provider']);
        $externalId = $validated['external_id']
            ?? ($provider->value === ExternalUserProvider::FAKE ? (string) Str::uuid() : null);

        if ($externalId === null) {
            throw ValidationException::withMessages([
                'external_id' => ['缺少付款人标识'],
            ]);
        }

        $payer = $upsertHandler->handle(
            $provider,
            $externalId,
            $validated['payer_name'] ?? null,
        );

        $result = $handler->handle(
            token: $token,
            payerExternalUserId: $payer->id ?? throw new \RuntimeException('External user id missing'),
            openid: $validated['openid'] ?? null,
            channel: $validated['channel'] ?? null,
        );

        return ApiResponse::success([
            'payment' => new PaymentResource($result['payment']),
            'pay_params' => $result['pay_params'],
        ]);
    }
}
