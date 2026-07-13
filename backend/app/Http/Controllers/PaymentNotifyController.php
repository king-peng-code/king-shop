<?php

namespace App\Http\Controllers;

use App\Application\Payment\HandleAlipayNotify\HandleAlipayNotifyHandler;
use App\Application\Payment\HandleWechatNotify\HandleWechatNotifyHandler;
use App\Domain\Payment\Exceptions\InvalidPaymentSignatureException;
use App\Infrastructure\Persistence\Eloquent\Models\ThirdPartyCallbackLogModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentNotifyController extends Controller
{
    public function __construct(
        private readonly ThirdPartyCallbackLogModel $logModel,
    ) {}

    public function alipay(Request $request, HandleAlipayNotifyHandler $handler): Response
    {
        return $this->handleWithLogging('alipay', $request, function () use ($request, $handler): Response {
            try {
                $handler->handle($request);
            } catch (InvalidPaymentSignatureException) {
                return response('failure', 422);
            }

            return response('success');
        });
    }

    public function wechat(Request $request, HandleWechatNotifyHandler $handler): Response
    {
        return $this->handleWithLogging('wechat', $request, function () use ($request, $handler): Response {
            try {
                $handler->handle($request);
            } catch (InvalidPaymentSignatureException) {
                return response(
                    '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[INVALID SIGN]]></return_msg></xml>',
                    422,
                    ['Content-Type' => 'application/xml'],
                );
            }

            return response(
                '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>',
                200,
                ['Content-Type' => 'application/xml'],
            );
        });
    }

    private function handleWithLogging(string $channel, Request $request, callable $next): Response
    {
        $response = $next();

        $this->logModel->create([
            'channel' => $channel,
            'request_method' => $request->method(),
            'request_headers' => json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE),
            'request_body' => $request->getContent(),
            'response_status' => $response->status(),
            'response_body' => $response->getContent(),
            'ip_address' => $request->ip(),
        ]);

        return $response;
    }
}
