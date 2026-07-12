<?php

namespace App\Application\Payment\QueryPendingPayments;

use App\Application\Payment\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentStatus;

class QueryPendingPaymentsHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayResolverInterface $gatewayResolver,
        private readonly ConfirmPaymentHandler $confirmPaymentHandler,
    ) {}

    public function handle(int $limit = 100): int
    {
        $payments = $this->paymentRepository->findPendingPayments($limit);
        $confirmed = 0;

        foreach ($payments as $payment) {
            $gateway = $this->gatewayResolver->resolve($payment->channel->value);
            $queryResult = $gateway->queryPayment($payment->outTradeNo);

            if ($queryResult->status->value !== PaymentStatus::SUCCESS || $queryResult->tradeNo === null) {
                continue;
            }

            $this->confirmPaymentHandler->handle(
                outTradeNo: $payment->outTradeNo,
                tradeNo: $queryResult->tradeNo,
                rawNotify: ['source' => 'query'],
            );
            $confirmed++;
        }

        return $confirmed;
    }
}
