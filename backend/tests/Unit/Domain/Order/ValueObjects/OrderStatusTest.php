<?php

namespace Tests\Unit\Domain\Order\ValueObjects;

use App\Domain\Order\ValueObjects\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    #[Test]
    public function paid_status_parses_correctly(): void
    {
        $status = OrderStatus::fromString('paid');
        $this->assertSame('paid', $status->value);
    }

    #[Test]
    public function invalid_status_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OrderStatus::fromString('invalid');
    }
}
