<?php

namespace Tests\Unit\Infrastructure\Payment\Signature;

use App\Infrastructure\Payment\Signature\WechatSigner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WechatSignerTest extends TestCase
{
    #[Test]
    public function sign_and_verify_round_trip(): void
    {
        $signer = new WechatSigner();
        $params = [
            'appid' => 'wx123',
            'mch_id' => '1900000109',
            'nonce_str' => 'abc',
            'out_trade_no' => 'PAY123',
            'total_fee' => '100',
        ];
        $params['sign'] = $signer->sign($params, 'test_api_key');

        $this->assertTrue($signer->verify($params, 'test_api_key'));
    }
}
