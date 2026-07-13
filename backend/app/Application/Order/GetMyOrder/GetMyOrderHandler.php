<?php

namespace App\Application\Order\GetMyOrder;

use App\Application\Order\CancelOrder\CancelOrderHandler;
use App\Application\Payment\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderAccessDeniedException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentStatus;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class GetMyOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly SystemConfigRepositoryInterface $configRepository,
        private readonly CancelOrderHandler $cancelOrderHandler,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayResolverInterface $gatewayResolver,
        private readonly ConfirmPaymentHandler $confirmPaymentHandler,
    ) {}

    public function handle(int $orderId, int $userId): Order
    {
        $order = $this->repository->findById($orderId);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        if ($order->userId !== $userId) {
            throw new OrderAccessDeniedException();
        }

        if ($order->status->value === OrderStatus::PENDING_PAYMENT) {
            $this->syncPaymentStatus($orderId);

            $order = $this->repository->findById($orderId);
        }

        if ($order->status->value === OrderStatus::PENDING_PAYMENT && $this->isExpired($order)) {
            if ($order->id !== null) {
                $this->cancelOrderHandler->handle($order->id, '超时未支付自动取消');
            }

            $order = $this->repository->findById($orderId);
        }

        return $order;
    }

    private function syncPaymentStatus(int $orderId): void
    {
        $payment = $this->paymentRepository->findPendingByOrderId($orderId);

        if ($payment === null) {
            return;
        }

        $gateway = $this->gatewayResolver->resolve($payment->channel->value);
        $queryResult = $gateway->queryPayment($payment->outTradeNo);

        if ($queryResult->status->value !== PaymentStatus::SUCCESS || $queryResult->tradeNo === null) {
            return;
        }

        $this->confirmPaymentHandler->handle(
            outTradeNo: $payment->outTradeNo,
            tradeNo: $queryResult->tradeNo,
            rawNotify: ['source' => 'sync_on_read'],
        );
    }

    private function isExpired(Order $order): bool
    {
        $config = $this->configRepository->findByGroupAndKey('order', 'auto_cancel_minutes');
        $minutes = max(1, (int) ($config?->value ?? 30));

        $cutoff = now()->subMinutes($minutes);
        $createdAt = $order->createdAt instanceof \DateTimeInterface
            ? $order->createdAt
            : new \DateTimeImmutable($order->createdAt);

        return $createdAt < \DateTimeImmutable::createFromMutable($cutoff);
    }
}
