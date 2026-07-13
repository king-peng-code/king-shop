<?php

namespace App\Application\Payment\InitiatePayment;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderAccessDeniedException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;
use App\Domain\Payment\DTO\PaymentCreateResult;
use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Exceptions\OrderNotPayableException;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Domain\Payment\ValueObjects\PaymentStatus;
use App\Infrastructure\Payment\OutTradeNoGenerator;

class InitiatePaymentHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayResolverInterface $gatewayResolver,
        private readonly OutTradeNoGenerator $outTradeNoGenerator,
    ) {}

    /**
     * @return array{payment: Payment, pay_params: array<string, mixed>}
     */
    public function handle(int $orderId, int $userId, ?string $channel = null): array
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        if ($order->userId !== $userId) {
            throw new OrderAccessDeniedException();
        }

        if ($order->paymentMethod->value === PaymentMethod::PROXY) {
            throw new OrderNotPayableException('代付订单请分享代付链接');
        }

        if ($order->status->value !== OrderStatus::PENDING_PAYMENT) {
            throw new OrderNotPayableException();
        }

        $gateway = $this->gatewayResolver->resolve($channel);
        $paymentChannel = PaymentChannel::fromString($gateway->channel());

        $existing = $this->paymentRepository->findPendingByOrderId($orderId);

        // If the existing pending payment uses a different channel, mark it as
        // failed so a new one gets created with the user's selected channel.
        if ($existing !== null && $existing->channel->value !== $paymentChannel->value) {
            $this->paymentRepository->save(new Payment(
                id: $existing->id,
                orderId: $existing->orderId,
                payerExternalUserId: $existing->payerExternalUserId,
                outTradeNo: $existing->outTradeNo,
                tradeNo: $existing->tradeNo,
                amount: $existing->amount,
                channel: $existing->channel,
                status: PaymentStatus::fromString(PaymentStatus::FAILED),
                paidAt: $existing->paidAt,
                rawNotify: $existing->rawNotify,
            ));
            $existing = null;
        }

        $payment = $existing ?? $this->paymentRepository->save(new Payment(
            id: null,
            orderId: $orderId,
            payerExternalUserId: null,
            outTradeNo: $this->outTradeNoGenerator->generate($orderId),
            tradeNo: null,
            amount: $order->totalAmount,
            channel: $paymentChannel,
            status: PaymentStatus::fromString(PaymentStatus::PENDING),
            paidAt: null,
            rawNotify: null,
        ));

        $result = $gateway->createPayment($payment, $order);

        return [
            'payment' => $payment,
            'pay_params' => $result->payParams,
        ];
    }
}
