<?php

namespace App\Application\Payment\HandleWechatNotify;

use App\Application\Payment\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Payment\Exceptions\InvalidPaymentSignatureException;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Infrastructure\Payment\PaymentChannelPolicy;
use Illuminate\Http\Request;

class HandleWechatNotifyHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayResolverInterface $gatewayResolver,
        private readonly ConfirmPaymentHandler $confirmPaymentHandler,
    ) {}

    public function handle(Request $request): void
    {
        $params = $this->parseXml($request->getContent());
        $outTradeNo = (string) ($params['out_trade_no'] ?? $request->input('out_trade_no', ''));
        $payment = $outTradeNo !== '' ? $this->paymentRepository->findByOutTradeNo($outTradeNo) : null;

        if ($payment !== null) {
            PaymentChannelPolicy::assertNotifyAllowed($payment);
        }

        $channel = $payment?->channel->value ?? PaymentChannel::WECHAT;

        $gateway = $this->gatewayResolver->resolve($channel);
        $result = $gateway->verifyNotify($request);

        if (! $result->verified) {
            throw new InvalidPaymentSignatureException();
        }

        // Valid notification but payment not successful: acknowledge receipt
        if ($result->outTradeNo === null || $result->tradeNo === null) {
            return;
        }

        $this->confirmPaymentHandler->handle(
            outTradeNo: $result->outTradeNo,
            tradeNo: $result->tradeNo,
            rawNotify: $result->rawPayload,
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseXml(string $xml): array
    {
        $xml = trim($xml);

        if ($xml === '' || ! str_starts_with($xml, '<')) {
            return [];
        }

        $element = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($element === false) {
            return [];
        }

        /** @var array<string, string> */
        return json_decode(json_encode($element), true) ?: [];
    }
}
