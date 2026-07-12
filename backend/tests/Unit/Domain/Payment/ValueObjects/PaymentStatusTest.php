<?php

namespace Tests\Unit\Domain\Payment\ValueObjects;

use App\Domain\Payment\ValueObjects\PaymentStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    #[Test]
    public function success_status_parses_correctly(): void
    {
        $status = PaymentStatus::fromString('success');
        $this->assertTrue($status->isSuccess());
    }

    #[Test]
    public function invalid_status_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PaymentStatus::fromString('invalid');
    }
}
