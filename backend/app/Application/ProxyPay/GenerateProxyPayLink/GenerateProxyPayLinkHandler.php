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
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\ProxyPay\ProxyPayExpiryCalculator;
use Illuminate\Support\Str;

class GenerateProxyPayLinkHandler
{
    private const PLACEHOLDER_BRAND_NAME = '{brand_name}';
    private const PLACEHOLDER_ORDER_NO = '{order_no}';
    private const PLACEHOLDER_AMOUNT = '{amount}';
    private const PLACEHOLDER_EXPIRES_AT = '{expires_at}';
    private const PLACEHOLDER_SHARE_URL = '{share_url}';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProxyPayTokenRepositoryInterface $tokenRepository,
        private readonly ProxyPayExpiryCalculator $expiryCalculator,
        private readonly SystemConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @return array{url: string, token: string, expires_at: string, share_title: string, share_message: string, share_copy_text: string}
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
        $shareUrl = "{$frontendUrl}/proxy-pay/{$token->token}";

        $placeholders = [
            self::PLACEHOLDER_BRAND_NAME => $this->resolveConfig('app', 'name', 'king-shop'),
            self::PLACEHOLDER_ORDER_NO => (string) $order->orderNo,
            self::PLACEHOLDER_AMOUNT => $this->formatAmount($order->totalAmount),
            self::PLACEHOLDER_EXPIRES_AT => $token->expiresAt->format('Y-m-d H:i'),
            self::PLACEHOLDER_SHARE_URL => $shareUrl,
        ];

        return [
            'url' => $shareUrl,
            'token' => $token->token,
            'expires_at' => $token->expiresAt->format(DATE_ATOM),
            'share_title' => $this->resolveConfig('order', 'share_title', '帮我付一下'),
            'share_message' => $this->formatTemplate('share_message', $placeholders),
            'share_copy_text' => $this->formatTemplate('share_copy_text', $placeholders),
        ];
    }

    private function resolveConfig(string $group, string $key, string $default): string
    {
        $config = $this->configRepository->findByGroupAndKey($group, $key);

        if ($config === null || $config->value === '') {
            return $default;
        }

        return $config->value;
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    private function formatTemplate(string $configKey, array $placeholders): string
    {
        $template = $this->resolveConfig('order', $configKey, $placeholders[self::PLACEHOLDER_SHARE_URL]);

        return strtr($template, $placeholders);
    }
}
