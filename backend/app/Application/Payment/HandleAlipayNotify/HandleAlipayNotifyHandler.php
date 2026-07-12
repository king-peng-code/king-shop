<?php

namespace App\Application\Payment\HandleAlipayNotify;

use App\Application\Payment\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Payment\Exceptions\InvalidPaymentSignatureException;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Infrastructure\Payment\PaymentChannelPolicy;
use Illuminate\Http\Request;

class HandleAlipayNotifyHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayResolverInterface $gatewayResolver,
        private readonly ConfirmPaymentHandler $confirmPaymentHandler,
    ) {}

    public function handle(Request $request): void
    {
        $outTradeNo = (string) $request->input('out_trade_no', '');
        $payment = $outTradeNo !== '' ? $this->paymentRepository->findByOutTradeNo($outTradeNo) : null;

        if ($payment !== null) {
            PaymentChannelPolicy::assertNotifyAllowed($payment);
        }

        $channel = $payment?->channel->value ?? PaymentChannel::ALIPAY_SANDBOX;

        $gateway = $this->gatewayResolver->resolve($channel);
        $result = $gateway->verifyNotify($request);

        if (! $result->verified || $result->outTradeNo === null || $result->tradeNo === null) {
            throw new InvalidPaymentSignatureException();
        }

        $this->confirmPaymentHandler->handle(
            outTradeNo: $result->outTradeNo,
            tradeNo: $result->tradeNo,
            rawNotify: $result->rawPayload,
        );
    }
}
