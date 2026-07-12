<?php

namespace App\Application\ProxyPay\GenerateProxyPayLink;

use App\Domain\Order\Exceptions\OrderAccessDeniedException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;
use App\Domain\Payment\Exceptions\OrderNotPayableException;
use App\Domain\ProxyPay\Entities\ProxyPayToken;
use App\Domain\ProxyPay\Exceptions\OrderNotProxyPayException;
use App\Domain\ProxyPay\Repositories\ProxyPayTokenRepositoryInterface;
use App\Infrastructure\ProxyPay\ProxyPayExpiryCalculator;
use Illuminate\Support\Str;

class GenerateProxyPayLinkHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProxyPayTokenRepositoryInterface $tokenRepository,
        private readonly ProxyPayExpiryCalculator $expiryCalculator,
    ) {}

    /**
     * @return array{url: string, token: string, expires_at: string}
     */
    public function handle(int $orderId, int $userId): array
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        if ($order->userId !== $userId) {
            throw new OrderAccessDeniedException();
        }

        if ($order->paymentMethod->value !== PaymentMethod::PROXY) {
            throw new OrderNotProxyPayException();
        }

        if ($order->status->value !== OrderStatus::PENDING_PAYMENT) {
            throw new OrderNotPayableException();
        }

        $expiresAt = $this->expiryCalculator->expiresAtForOrder($order);
        $existing = $this->tokenRepository->findActiveByOrderId($orderId);

        $token = $existing ?? $this->tokenRepository->save(new ProxyPayToken(
            id: null,
            orderId: $orderId,
            token: Str::random(48),
            expiresAt: $expiresAt,
            createdAt: new \DateTimeImmutable,
        ));

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return [
            'url' => "{$frontendUrl}/proxy-pay/{$token->token}",
            'token' => $token->token,
            'expires_at' => $token->expiresAt->format(DATE_ATOM),
        ];
    }
}
