<?php

namespace Tests\Unit\Domain\ExternalUser\ValueObjects;

use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExternalUserProviderTest extends TestCase
{
    #[Test]
    public function wechat_provider_parses_correctly(): void
    {
        $provider = ExternalUserProvider::fromString('wechat');

        $this->assertSame('wechat', $provider->value);
    }

    #[Test]
    public function alipay_provider_parses_correctly(): void
    {
        $provider = ExternalUserProvider::fromString('alipay');

        $this->assertSame('alipay', $provider->value);
    }

    #[Test]
    public function fake_provider_parses_correctly(): void
    {
        $provider = ExternalUserProvider::fromString('fake');

        $this->assertSame('fake', $provider->value);
    }

    #[Test]
    public function invalid_provider_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExternalUserProvider::fromString('invalid');
    }
}
