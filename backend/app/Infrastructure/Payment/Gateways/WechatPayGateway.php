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
use App\Infrastructure\Payment\Signature\WechatSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WechatPayGateway implements PaymentGatewayInterface
{
    private const UNIFIED_ORDER_URL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    private const ORDER_QUERY_URL = 'https://api.mch.weixin.qq.com/pay/orderquery';

    public function __construct(
        private readonly PaymentConfigReader $config,
        private readonly WechatSigner $signer,
    ) {}

    public function channel(): string
    {
        return PaymentChannel::WECHAT;
    }

    public function createPayment(Payment $payment, Order $order, array $options = []): PaymentCreateResult
    {
        $tradeType = strtoupper((string) ($options['trade_type'] ?? 'APP'));

        $params = [
            'appid' => $this->config->get('wechat.app_id'),
            'mch_id' => $this->config->get('wechat.mch_id'),
            'nonce_str' => Str::random(32),
            'body' => '订单 '.$order->orderNo,
            'out_trade_no' => $payment->outTradeNo,
            'total_fee' => (string) $payment->amount,
            'spbill_create_ip' => '127.0.0.1',
            'notify_url' => $this->config->notifyBaseUrl().'/api/v1/payments/notify/wechat',
            'trade_type' => $tradeType,
        ];

        if ($tradeType === 'JSAPI') {
            $openid = (string) ($options['openid'] ?? '');
            if ($openid === '') {
                throw new \InvalidArgumentException('JSAPI 支付需要 openid');
            }
            $params['openid'] = $openid;
        }

        $params['sign'] = $this->signer->sign($params, $this->config->get('wechat.api_key'));

        $response = Http::withBody($this->toXml($params), 'application/xml')
            ->post(self::UNIFIED_ORDER_URL);

        $result = $this->parseXml($response->body());
        if (($result['return_code'] ?? '') !== 'SUCCESS' || ($result['result_code'] ?? '') !== 'SUCCESS') {
            throw new \RuntimeException('WeChat unified order failed: '.($result['return_msg'] ?? 'unknown'));
        }

        if ($tradeType === 'JSAPI') {
            $jsapiParams = [
                'appId' => $params['appid'],
                'timeStamp' => (string) time(),
                'nonceStr' => Str::random(32),
                'package' => 'prepay_id='.$result['prepay_id'],
                'signType' => 'MD5',
            ];
            $jsapiParams['paySign'] = $this->signer->sign([
                'appId' => $jsapiParams['appId'],
                'timeStamp' => $jsapiParams['timeStamp'],
                'nonceStr' => $jsapiParams['nonceStr'],
                'package' => $jsapiParams['package'],
                'signType' => $jsapiParams['signType'],
            ], $this->config->get('wechat.api_key'));

            return new PaymentCreateResult(
                outTradeNo: $payment->outTradeNo,
                payParams: [
                    'channel' => PaymentChannel::WECHAT,
                    'trade_type' => 'JSAPI',
                    'jsapi' => $jsapiParams,
                ],
            );
        }

        $prepayParams = [
            'appid' => $params['appid'],
            'partnerid' => $params['mch_id'],
            'prepayid' => $result['prepay_id'],
            'package' => 'Sign=WXPay',
            'noncestr' => Str::random(32),
            'timestamp' => (string) time(),
        ];
        $prepayParams['sign'] = $this->signer->sign($prepayParams, $this->config->get('wechat.api_key'));

        return new PaymentCreateResult(
            outTradeNo: $payment->outTradeNo,
            payParams: [
                'channel' => PaymentChannel::WECHAT,
                'prepay' => $prepayParams,
            ],
        );
    }

    public function queryPayment(string $outTradeNo): PaymentQueryResult
    {
        $params = [
            'appid' => $this->config->get('wechat.app_id'),
            'mch_id' => $this->config->get('wechat.mch_id'),
            'out_trade_no' => $outTradeNo,
            'nonce_str' => Str::random(32),
        ];
        $params['sign'] = $this->signer->sign($params, $this->config->get('wechat.api_key'));

        $response = Http::withBody($this->toXml($params), 'application/xml')
            ->post(self::ORDER_QUERY_URL);

        $result = $this->parseXml($response->body());
        if (($result['trade_state'] ?? '') === 'SUCCESS') {
            return PaymentQueryResult::success((string) ($result['transaction_id'] ?? ''));
        }

        if (($result['trade_state'] ?? '') === 'NOTPAY') {
            return PaymentQueryResult::pending();
        }

        return PaymentQueryResult::failed();
    }

    public function verifyNotify(Request $request): NotifyVerifyResult
    {
        $xml = $request->getContent();
        $params = $this->parseXml($xml);

        if (! $this->signer->verify($params, $this->config->get('wechat.api_key'))) {
            return NotifyVerifyResult::failure();
        }

        if (($params['return_code'] ?? '') !== 'SUCCESS' || ($params['result_code'] ?? '') !== 'SUCCESS') {
            return NotifyVerifyResult::failure();
        }

        return NotifyVerifyResult::success(
            (string) $params['out_trade_no'],
            (string) ($params['transaction_id'] ?? ''),
            $params,
        );
    }

    /**
     * @param  array<string, string>  $params
     */
    private function toXml(array $params): string
    {
        $xml = '<xml>';
        foreach ($params as $key => $value) {
            $xml .= '<'.$key.'><![CDATA['.$value.']]></'.$key.'>';
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * @return array<string, string>
     */
    private function parseXml(string $xml): array
    {
        if ($xml === '') {
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
