<?php

namespace App\Http\Controllers;

use App\Application\Payment\HandleAlipayNotify\HandleAlipayNotifyHandler;
use App\Application\Payment\HandleWechatNotify\HandleWechatNotifyHandler;
use App\Domain\Payment\Exceptions\InvalidPaymentSignatureException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentNotifyController extends Controller
{
    public function alipay(Request $request, HandleAlipayNotifyHandler $handler): Response
    {
        try {
            $handler->handle($request);
        } catch (InvalidPaymentSignatureException) {
            return response('failure', 422);
        }

        return response('success');
    }

    public function wechat(Request $request, HandleWechatNotifyHandler $handler): Response
    {
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
    }
}
