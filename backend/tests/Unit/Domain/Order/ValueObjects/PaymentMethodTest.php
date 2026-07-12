<?php

namespace Tests\Unit\Domain\Order\ValueObjects;

use App\Domain\Order\ValueObjects\PaymentMethod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    #[Test]
    public function self_method_parses_correctly(): void
    {
        $method = PaymentMethod::fromString('self');
        $this->assertSame('self', $method->value);
    }

    #[Test]
    public function proxy_method_parses_correctly(): void
    {
        $method = PaymentMethod::fromString('proxy');
        $this->assertSame('proxy', $method->value);
    }

    #[Test]
    public function invalid_method_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PaymentMethod::fromString('invalid');
    }
}
