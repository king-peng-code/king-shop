<?php

namespace Tests\Unit\Domain\SystemConfig\Entities;

use App\Domain\SystemConfig\Entities\SystemConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SystemConfigTest extends TestCase
{
    #[Test]
    public function non_sensitive_config_returns_plain_display_value(): void
    {
        $config = new SystemConfig(
            group: 'app',
            key: 'name',
            value: '内部下午茶',
            isSensitive: false,
            description: '商城名称',
        );

        $this->assertSame('内部下午茶', $config->displayValue());
    }

    #[Test]
    public function sensitive_config_with_value_is_masked(): void
    {
        $config = new SystemConfig(
            group: 'payment',
            key: 'wechat.mch_id',
            value: '1234567890',
            isSensitive: true,
            description: '微信商户号',
        );

        $this->assertSame('****', $config->displayValue());
    }

    #[Test]
    public function sensitive_config_with_empty_value_returns_empty_string(): void
    {
        $config = new SystemConfig(
            group: 'payment',
            key: 'wechat.mch_id',
            value: '',
            isSensitive: true,
            description: '微信商户号',
        );

        $this->assertSame('', $config->displayValue());
    }
}
