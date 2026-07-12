<?php

namespace App\Application\ProxyPay\InitiateProxyPayment;

use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Exceptions\OrderNotPayableException;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Domain\Payment\ValueObjects\PaymentStatus;
use App\Domain\ProxyPay\Exceptions\ProxyPayLinkExpiredException;
use App\Domain\ProxyPay\Exceptions\ProxyPayTokenNotFoundException;
use App\Domain\ProxyPay\Repositories\ProxyPayTokenRepositoryInterface;
use App\Infrastructure\Payment\OutTradeNoGenerator;

class InitiateProxyPaymentHandler
{
    public function __construct(
        private readonly ProxyPayTokenRepositoryInterface $tokenRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayResolverInterface $gatewayResolver,
        private readonly OutTradeNoGenerator $outTradeNoGenerator,
    ) {}

    /**
     * @return array{payment: Payment, pay_params: array<string, mixed>}
     */
    public function handle(string $token, int $payerExternalUserId, ?string $openid = null, ?string $channel = null): array
    {
        $proxyToken = $this->tokenRepository->findByToken($token);

        if ($proxyToken === null) {
            throw new ProxyPayTokenNotFoundException();
        }

        if ($proxyToken->isExpired(\DateTimeImmutable::createFromMutable(now()))) {
            throw new ProxyPayLinkExpiredException();
        }

        $order = $this->orderRepository->findById($proxyToken->orderId);

        if ($order === null || $order->status->value !== OrderStatus::PENDING_PAYMENT) {
            throw new OrderNotPayableException();
        }

        $gateway = $this->gatewayResolver->resolve($channel ?? PaymentChannel::WECHAT);
        $paymentChannel = PaymentChannel::fromString($gateway->channel());

        $existing = $this->paymentRepository->findPendingByOrderId($order->id ?? throw new \RuntimeException('Order id missing'));

        if ($existing !== null) {
            $payment = $this->paymentRepository->save(new Payment(
                id: $existing->id,
                orderId: $existing->orderId,
                payerExternalUserId: $payerExternalUserId,
                outTradeNo: $existing->outTradeNo,
                tradeNo: $existing->tradeNo,
                amount: $existing->amount,
                channel: $existing->channel,
                status: $existing->status,
                paidAt: $existing->paidAt,
                rawNotify: $existing->rawNotify,
            ));
        } else {
            $payment = $this->paymentRepository->save(new Payment(
                id: null,
                orderId: $order->id,
                payerExternalUserId: $payerExternalUserId,
                outTradeNo: $this->outTradeNoGenerator->generate($order->id),
                tradeNo: null,
                amount: $order->totalAmount,
                channel: $paymentChannel,
                status: PaymentStatus::fromString(PaymentStatus::PENDING),
                paidAt: null,
                rawNotify: null,
            ));
        }

        $result = $gateway->createPayment($payment, $order, [
            'trade_type' => 'JSAPI',
            'openid' => $openid ?? '',
        ]);

        return [
            'payment' => $payment,
            'pay_params' => $result->payParams,
        ];
    }
}
