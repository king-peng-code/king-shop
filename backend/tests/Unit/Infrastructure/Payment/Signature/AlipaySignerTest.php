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
            'timestamp' => '2026-07-12 14:30:00',
            'version' => '1.0',
            'biz_content' => '{"out_trade_no":"PAY123"}',
        ];
        $params['sign'] = $signer->sign($params, $privateKeyPem);

        $this->assertTrue($signer->verify($params, $publicKeyPem));
    }

    #[Test]
    public function sign_includes_sign_type_in_content(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($privateKey, $privateKeyPem);

        $signer = new AlipaySigner();

        $paramsWithSignType = [
            'app_id' => '2021000000000000',
            'method' => 'alipay.trade.query',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => '2026-07-12 14:30:00',
            'version' => '1.0',
            'biz_content' => '{"out_trade_no":"PAY123"}',
        ];

        $paramsWithoutSignType = $paramsWithSignType;
        unset($paramsWithoutSignType['sign_type']);

        // The sign() method now includes sign_type in the signed content,
        // so signing with and without sign_type should produce different signatures
        $sign1 = $signer->sign($paramsWithSignType, $privateKeyPem);
        $sign2 = $signer->sign($paramsWithoutSignType, $privateKeyPem);

        $this->assertNotSame($sign1, $sign2, 'sign_type should affect the signature content');
    }

    #[Test]
    public function verify_excludes_sign_type_for_notify(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($privateKey, $privateKeyPem);
        $details = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $details['key'];

        $signer = new AlipaySigner();

        // Simulate Alipay signing the notify WITHOUT sign_type (standard behavior)
        $paramsWithoutSignType = [
            'app_id' => '2021000000000000',
            'method' => 'alipay.trade.query',
            'charset' => 'utf-8',
            'timestamp' => '2026-07-12 14:30:00',
            'version' => '1.0',
            'biz_content' => '{"out_trade_no":"PAY123"}',
        ];
        $alipaySignature = $signer->sign($paramsWithoutSignType, $privateKeyPem);

        // Alipay sends the notify WITH sign_type in the params
        $notifyParams = $paramsWithoutSignType;
        $notifyParams['sign_type'] = 'RSA2';
        $notifyParams['sign'] = $alipaySignature;

        // verify() should exclude sign_type and match Alipay's signature
        $this->assertTrue($signer->verify($notifyParams, $publicKeyPem));
    }
}
