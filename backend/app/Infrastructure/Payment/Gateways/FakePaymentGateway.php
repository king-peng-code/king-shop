<?php

namespace App\Infrastructure\Payment\Gateways;

use App\Domain\Order\Entities\Order;
use App\Domain\Payment\DTO\NotifyVerifyResult;
use App\Domain\Payment\DTO\PaymentCreateResult;
use App\Domain\Payment\DTO\PaymentQueryResult;
use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Services\PaymentGatewayInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use Illuminate\Http\Request;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public function channel(): string
    {
        $this->assertNotProduction();

        return PaymentChannel::FAKE;
    }

    public function createPayment(Payment $payment, Order $order, array $options = []): PaymentCreateResult
    {
        $this->assertNotProduction();

        $tradeType = $options['trade_type'] ?? 'JSAPI';

        return new PaymentCreateResult(
            outTradeNo: $payment->outTradeNo,
            payParams: [
                'channel' => PaymentChannel::FAKE,
                'trade_type' => $tradeType,
                'out_trade_no' => $payment->outTradeNo,
                'amount' => $payment->amount,
                'order_no' => $order->orderNo,
            ],
        );
    }

    public function queryPayment(string $outTradeNo): PaymentQueryResult
    {
        $this->assertNotProduction();

        return PaymentQueryResult::success('FAKE_'.$outTradeNo);
    }

    public function verifyNotify(Request $request): NotifyVerifyResult
    {
        $this->assertNotProduction();

        if ($request->input('trade_status') !== 'TRADE_SUCCESS') {
            return NotifyVerifyResult::failure();
        }

        $outTradeNo = (string) $request->input('out_trade_no');
        $tradeNo = (string) ($request->input('trade_no') ?: 'FAKE_'.$outTradeNo);

        return NotifyVerifyResult::success($outTradeNo, $tradeNo, $request->all());
    }

    private function assertNotProduction(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Fake payment gateway is not allowed in production');
        }
    }
}
