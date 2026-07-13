<?php

namespace Tests\Unit\Infrastructure\Payment;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Payment\PaymentConfigReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentConfigReaderTest extends TestCase
{
    private function createReader(array $configs): PaymentConfigReader
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')->willReturnCallback(
            function (string $group, string $key) use ($configs) {
                foreach ($configs as $cfg) {
                    if ($cfg['group'] === $group && $cfg['key'] === $key) {
                        return new SystemConfig(
                            group: $cfg['group'],
                            key: $cfg['key'],
                            value: $cfg['value'],
                            isSensitive: $cfg['is_sensitive'] ?? false,
                            description: $cfg['description'] ?? null,
                        );
                    }
                }
                return null;
            },
        );

        return new PaymentConfigReader($repository);
    }

    #[Test]
    public function is_enabled_returns_true_when_enabled_is_1(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '1'],
        ]);

        $this->assertTrue($reader->isEnabled('alipay_sandbox'));
    }

    #[Test]
    public function is_enabled_returns_false_when_enabled_is_0(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '0'],
        ]);

        $this->assertFalse($reader->isEnabled('alipay_sandbox'));
    }

    #[Test]
    public function is_enabled_returns_false_when_enabled_not_set(): void
    {
        $reader = $this->createReader([]);

        $this->assertFalse($reader->isEnabled('alipay_sandbox'));
        $this->assertFalse($reader->isEnabled('wechat'));
    }

    #[Test]
    public function is_enabled_returns_false_for_unknown_channel(): void
    {
        $reader = $this->createReader([]);

        $this->assertFalse($reader->isEnabled('unknown_channel'));
    }

    #[Test]
    public function is_configured_returns_true_when_all_required_keys_filled(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '2021000000000000'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => 'MIICX...', 'is_sensitive' => true],
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => 'MIIBI...', 'is_sensitive' => true],
        ]);

        $this->assertTrue($reader->isConfigured('alipay_sandbox'));
    }

    #[Test]
    public function is_configured_returns_false_when_required_key_missing(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '2021000000000000'],
            // private_key missing
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => 'MIIBI...', 'is_sensitive' => true],
        ]);

        $this->assertFalse($reader->isConfigured('alipay_sandbox'));
    }

    #[Test]
    public function is_configured_returns_false_when_required_key_empty(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => ''],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => 'MIICX...', 'is_sensitive' => true],
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => 'MIIBI...', 'is_sensitive' => true],
        ]);

        $this->assertFalse($reader->isConfigured('alipay_sandbox'));
    }

    #[Test]
    public function is_configured_returns_false_for_unknown_channel(): void
    {
        $reader = $this->createReader([]);

        $this->assertFalse($reader->isConfigured('unknown_channel'));
    }

    #[Test]
    public function is_available_returns_true_when_enabled_and_configured(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '2021000000000000'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => 'MIICX...', 'is_sensitive' => true],
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => 'MIIBI...', 'is_sensitive' => true],
        ]);

        $this->assertTrue($reader->isAvailable('alipay_sandbox'));
    }

    #[Test]
    public function is_available_returns_false_when_not_enabled(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '0'],
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '2021000000000000'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => 'MIICX...', 'is_sensitive' => true],
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => 'MIIBI...', 'is_sensitive' => true],
        ]);

        $this->assertFalse($reader->isAvailable('alipay_sandbox'));
    }

    #[Test]
    public function is_available_returns_false_when_not_configured(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '1'],
            // app_id and keys not set
        ]);

        $this->assertFalse($reader->isAvailable('alipay_sandbox'));
    }

    #[Test]
    public function is_configured_for_wechat(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'wechat.app_id', 'value' => 'wx123456'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1600000000'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => 'abc123def456', 'is_sensitive' => true],
        ]);

        $this->assertTrue($reader->isConfigured('wechat'));
    }

    #[Test]
    public function wechat_not_configured_when_missing_app_id(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1600000000'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => 'abc123def456', 'is_sensitive' => true],
        ]);

        $this->assertFalse($reader->isConfigured('wechat'));
    }

    #[Test]
    public function mode_returns_sandbox_by_default(): void
    {
        $reader = $this->createReader([]);

        $this->assertSame('sandbox', $reader->mode('alipay_sandbox'));
        $this->assertSame('sandbox', $reader->mode('wechat'));
    }

    #[Test]
    public function mode_returns_sandbox_when_configured(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.mode', 'value' => 'sandbox'],
            ['group' => 'payment', 'key' => 'wechat.mode', 'value' => 'sandbox'],
        ]);

        $this->assertSame('sandbox', $reader->mode('alipay_sandbox'));
        $this->assertTrue($reader->isSandbox('alipay_sandbox'));
        $this->assertFalse($reader->isProduction('alipay_sandbox'));
    }

    #[Test]
    public function mode_returns_production_when_configured(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.mode', 'value' => 'production'],
            ['group' => 'payment', 'key' => 'wechat.mode', 'value' => 'production'],
        ]);

        $this->assertSame('production', $reader->mode('alipay_sandbox'));
        $this->assertTrue($reader->isProduction('alipay_sandbox'));
        $this->assertFalse($reader->isSandbox('alipay_sandbox'));
        $this->assertSame('production', $reader->mode('wechat'));
    }

    #[Test]
    public function mode_returns_sandbox_for_invalid_value(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.mode', 'value' => 'invalid_mode'],
        ]);

        $this->assertSame('sandbox', $reader->mode('alipay_sandbox'));
    }

    #[Test]
    public function mode_returns_sandbox_for_unknown_channel(): void
    {
        $reader = $this->createReader([]);

        $this->assertSame('sandbox', $reader->mode('unknown_channel'));
    }

    #[Test]
    public function alipay_gateway_url_returns_sandbox_url_by_default(): void
    {
        $reader = $this->createReader([]);

        $this->assertStringContainsString('openapi-sandbox', $reader->alipayGatewayUrl());
    }

    #[Test]
    public function alipay_gateway_url_returns_production_url_when_configured(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'alipay.mode', 'value' => 'production'],
        ]);

        $this->assertSame('https://openapi.dl.alipay.com/gateway.do', $reader->alipayGatewayUrl());
    }

    #[Test]
    public function wechat_unified_order_url_returns_sandbox_url_by_default(): void
    {
        $reader = $this->createReader([]);

        $this->assertStringContainsString('sandboxnew', $reader->wechatUnifiedOrderUrl());
    }

    #[Test]
    public function wechat_unified_order_url_returns_production_url_when_configured(): void
    {
        $reader = $this->createReader([
            ['group' => 'payment', 'key' => 'wechat.mode', 'value' => 'production'],
        ]);

        $this->assertSame('https://api.mch.weixin.qq.com/pay/unifiedorder', $reader->wechatUnifiedOrderUrl());
    }
}
