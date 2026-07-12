<?php

namespace App\Application\ProxyPay\GetProxyPayPreview;

use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\ProxyPay\Exceptions\ProxyPayLinkExpiredException;
use App\Domain\ProxyPay\Exceptions\ProxyPayTokenNotFoundException;
use App\Domain\ProxyPay\Repositories\ProxyPayTokenRepositoryInterface;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class GetProxyPayPreviewHandler
{
    public function __construct(
        private readonly ProxyPayTokenRepositoryInterface $tokenRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SystemConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @return array{
     *     total_amount: int,
     *     status: string,
     *     buyer_name: string|null,
     *     brand_name: string,
     *     items_summary: string,
     *     expires_at: string,
     *     payable: bool
     * }
     */
    public function handle(string $token): array
    {
        $proxyToken = $this->tokenRepository->findByToken($token);

        if ($proxyToken === null) {
            throw new ProxyPayTokenNotFoundException();
        }

        if ($proxyToken->isExpired(\DateTimeImmutable::createFromMutable(now()))) {
            throw new ProxyPayLinkExpiredException();
        }

        $order = $this->orderRepository->findById($proxyToken->orderId);

        if ($order === null) {
            throw new ProxyPayTokenNotFoundException();
        }

        $itemsSummary = $this->buildItemsSummary($order->items);
        $brandConfig = $this->configRepository->findByGroupAndKey('app', 'name');

        return [
            'total_amount' => $order->totalAmount,
            'status' => $order->status->value,
            'buyer_name' => $this->maskName($order->userName),
            'brand_name' => $brandConfig?->value ?: config('app.name', 'king-shop'),
            'items_summary' => $itemsSummary,
            'expires_at' => $proxyToken->expiresAt->format(DATE_ATOM),
            'payable' => $order->status->value === OrderStatus::PENDING_PAYMENT,
        ];
    }

    private function maskName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (mb_strlen($name) === 1) {
            return '*';
        }

        return mb_substr($name, 0, 1) . str_repeat('*', min(mb_strlen($name) - 1, 2));
    }

    /**
     * @param  \App\Domain\Order\Entities\OrderItem[]  $items
     */
    private function buildItemsSummary(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $count = count($items);

        if ($count === 1) {
            $item = $items[0];

            return $item->quantity > 1
                ? "{$item->productName} x{$item->quantity}"
                : $item->productName;
        }

        $first = $items[0]->productName;

        return "{$first} 等{$count}件商品";
    }
}
