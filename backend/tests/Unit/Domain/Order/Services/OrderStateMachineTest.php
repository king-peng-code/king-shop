<?php

namespace Tests\Unit\Domain\Order\Services;

use App\Domain\Order\Exceptions\InvalidOrderTransitionException;
use App\Domain\Order\Services\OrderStateMachine;
use App\Domain\Order\ValueObjects\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    #[Test]
    public function paid_can_transition_to_preparing(): void
    {
        $machine = new OrderStateMachine();
        $result = $machine->transition(
            OrderStatus::fromString('paid'),
            OrderStatus::fromString('preparing'),
        );
        $this->assertSame('preparing', $result->value);
    }

    #[Test]
    public function paid_cannot_transition_to_cancelled(): void
    {
        $machine = new OrderStateMachine();
        $this->expectException(InvalidOrderTransitionException::class);
        $machine->transition(
            OrderStatus::fromString('paid'),
            OrderStatus::fromString('cancelled'),
        );
    }

    #[Test]
    public function pending_payment_can_transition_to_cancelled(): void
    {
        $machine = new OrderStateMachine();
        $result = $machine->transition(
            OrderStatus::fromString('pending_payment'),
            OrderStatus::fromString('cancelled'),
        );
        $this->assertSame('cancelled', $result->value);
    }
}
