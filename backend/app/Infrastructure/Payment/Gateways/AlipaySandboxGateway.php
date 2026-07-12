<?php

namespace App\Infrastructure\Payment\Gateways;

use App\Domain\Order\Entities\Order;
use App\Domain\Payment\DTO\NotifyVerifyResult;
use App\Domain\Payment\DTO\PaymentCreateResult;
use App\Domain\Payment\DTO\PaymentQueryResult;
use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Services\PaymentGatewayInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Infrastructure\Payment\PaymentConfigReader;
use App\Infrastructure\Payment\Signature\AlipaySigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AlipaySandboxGateway implements PaymentGatewayInterface
{
    private const GATEWAY_URL = 'https://openapi-sandbox.dl.alipaydev.com/gateway.do';

    public function __construct(
        private readonly PaymentConfigReader $config,
        private readonly AlipaySigner $signer,
    ) {}

    public function channel(): string
    {
        return PaymentChannel::ALIPAY_SANDBOX;
    }

    public function createPayment(Payment $payment, Order $order): PaymentCreateResult
    {
        $bizContent = json_encode([
            'out_trade_no' => $payment->outTradeNo,
            'total_amount' => number_format($payment->amount / 100, 2, '.', ''),
            'subject' => '订单 '.$order->orderNo,
            'product_code' => 'QUICK_WAP_WAY',
        ], JSON_UNESCAPED_UNICODE);

        $params = [
            'app_id' => $this->config->get('alipay.app_id'),
            'method' => 'alipay.trade.wap.pay',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $this->config->notifyBaseUrl().'/api/v1/payments/notify/alipay',
            'biz_content' => $bizContent,
        ];

        $params['sign'] = $this->signer->sign($params, $this->config->get('alipay.private_key'));
        $query = http_build_query($params);

        return new PaymentCreateResult(
            outTradeNo: $payment->outTradeNo,
            payParams: [
                'channel' => PaymentChannel::ALIPAY_SANDBOX,
                'pay_url' => self::GATEWAY_URL.'?'.$query,
            ],
        );
    }

    public function queryPayment(string $outTradeNo): PaymentQueryResult
    {
        $bizContent = json_encode(['out_trade_no' => $outTradeNo], JSON_UNESCAPED_UNICODE);
        $params = [
            'app_id' => $this->config->get('alipay.app_id'),
            'method' => 'alipay.trade.query',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => $bizContent,
        ];
        $params['sign'] = $this->signer->sign($params, $this->config->get('alipay.private_key'));

        $response = Http::asForm()->post(self::GATEWAY_URL, $params);
        if (! $response->successful()) {
            return PaymentQueryResult::failed();
        }

        $payload = $response->json();
        $trade = $payload['alipay_trade_query_response'] ?? [];
        if (($trade['trade_status'] ?? '') === 'TRADE_SUCCESS') {
            return PaymentQueryResult::success((string) ($trade['trade_no'] ?? ''));
        }

        return PaymentQueryResult::pending();
    }

    public function verifyNotify(Request $request): NotifyVerifyResult
    {
        /** @var array<string, string> $params */
        $params = $request->all();

        if (! $this->signer->verify($params, $this->config->get('alipay.public_key'))) {
            return NotifyVerifyResult::failure();
        }

        if (($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return NotifyVerifyResult::failure();
        }

        return NotifyVerifyResult::success(
            (string) $params['out_trade_no'],
            (string) ($params['trade_no'] ?? ''),
            $params,
        );
    }
}
