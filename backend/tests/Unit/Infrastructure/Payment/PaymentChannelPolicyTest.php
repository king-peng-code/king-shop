<?php

namespace Tests\Unit\Infrastructure\Payment;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Payment\PaymentChannelPolicy;
use App\Infrastructure\Payment\PaymentConfigReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentChannelPolicyTest extends TestCase
{
    private function createPolicy(array $configs): PaymentChannelPolicy
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

        return new PaymentChannelPolicy(new PaymentConfigReader($repository));
    }

    #[Test]
    public function self_pay_returns_only_alipay_when_only_alipay_enabled_and_configured(): void
    {
        $policy = $this->createPolicy([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '2021000000000000'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => 'MIICX...'],
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => 'MIIBI...'],
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '0'],
        ]);

        $channels = $policy->selfPayChannels();

        $this->assertContains('alipay_sandbox', $channels);
        $this->assertNotContains('wechat', $channels);
    }

    #[Test]
    public function self_pay_returns_only_wechat_when_only_wechat_enabled_and_configured(): void
    {
        $policy = $this->createPolicy([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '0'],
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'wechat.app_id', 'value' => 'wx123456'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1600000000'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => 'abc123'],
        ]);

        $channels = $policy->selfPayChannels();

        $this->assertNotContains('alipay_sandbox', $channels);
        $this->assertContains('wechat', $channels);
    }

    #[Test]
    public function self_pay_returns_both_when_both_enabled_and_configured(): void
    {
        $policy = $this->createPolicy([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '2021000000000000'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => 'MIICX...'],
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => 'MIIBI...'],
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'wechat.app_id', 'value' => 'wx123456'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1600000000'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => 'abc123'],
        ]);

        $channels = $policy->selfPayChannels();

        $this->assertContains('alipay_sandbox', $channels);
        $this->assertContains('wechat', $channels);
    }

    #[Test]
    public function self_pay_returns_empty_when_none_enabled(): void
    {
        $policy = $this->createPolicy([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '0'],
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '0'],
        ]);

        $channels = $policy->selfPayChannels();

        // fake is always added in test environment
        $this->assertSame(['fake'], $channels);
    }

    #[Test]
    public function self_pay_excludes_channel_when_enabled_but_not_configured(): void
    {
        $policy = $this->createPolicy([
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '1'],
            // alipay.app_id is missing — not configured
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'wechat.app_id', 'value' => 'wx123456'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1600000000'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => 'abc123'],
        ]);

        $channels = $policy->selfPayChannels();

        $this->assertNotContains('alipay_sandbox', $channels);
        $this->assertContains('wechat', $channels);
    }

    #[Test]
    public function proxy_pay_returns_wechat_when_enabled_and_configured(): void
    {
        $policy = $this->createPolicy([
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'wechat.app_id', 'value' => 'wx123456'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1600000000'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => 'abc123'],
        ]);

        $channels = $policy->proxyPayChannels();

        $this->assertContains('wechat', $channels);
    }

    #[Test]
    public function proxy_pay_excludes_wechat_when_not_enabled(): void
    {
        $policy = $this->createPolicy([
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '0'],
        ]);

        $channels = $policy->proxyPayChannels();

        $this->assertNotContains('wechat', $channels);
    }
}
