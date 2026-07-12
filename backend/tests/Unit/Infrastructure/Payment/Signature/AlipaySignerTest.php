<?php

namespace Tests\Unit\Infrastructure\Payment\Signature;

use App\Infrastructure\Payment\Signature\AlipaySigner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlipaySignerTest extends TestCase
{
    #[Test]
    public function sign_and_verify_round_trip(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($privateKey);

        openssl_pkey_export($privateKey, $privateKeyPem);
        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $publicKeyPem = $details['key'];

        $signer = new AlipaySigner();
        $params = [
            'app_id' => '2021000000000000',
            'method' => 'alipay.trade.query',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => '2026-07-12 14:30:00',
            'version' => '1.0',
            'biz_content' => '{"out_trade_no":"PAY123"}',
        ];
        $params['sign'] = $signer->sign($params, $privateKeyPem);

        $this->assertTrue($signer->verify($params, $publicKeyPem));
    }
}
