<?php

namespace App\Application\ProxyPay\GetProxyPayPreview;

use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\ProxyPay\Exceptions\ProxyPayLinkExpiredException;
use App\Domain\ProxyPay\Exceptions\ProxyPayTokenNotFoundException;
use App\Domain\ProxyPay\Repositories\ProxyPayTokenRepositoryInterface;

class GetProxyPayPreviewHandler
{
    public function __construct(
        private readonly ProxyPayTokenRepositoryInterface $tokenRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @return array{
     *     order_no: string,
     *     total_amount: int,
     *     status: string,
     *     buyer_name: string|null,
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

        return [
            'order_no' => $order->orderNo,
            'total_amount' => $order->totalAmount,
            'status' => $order->status->value,
            'buyer_name' => $order->userName,
            'expires_at' => $proxyToken->expiresAt->format(DATE_ATOM),
            'payable' => $order->status->value === OrderStatus::PENDING_PAYMENT,
        ];
    }
}
