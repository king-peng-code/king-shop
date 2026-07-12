<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\CancelOrder\CancelOrderHandler;
use App\Application\Order\CompleteOrder\CompleteOrderHandler;
use App\Application\Order\MarkOrderPreparing\MarkOrderPreparingHandler;
use App\Application\Order\MarkOrderReady\MarkOrderReadyHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\InvalidOrderTransitionException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\Services\OrderStateMachine;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStatusHandlersTest extends TestCase
{
    private function makeOrder(OrderStatus $status): Order
    {
        return new Order(
            id: 1,
            orderNo: 'KS202607121430001',
            userId: 5,
            totalAmount: 1500,
            status: $status,
            paymentMethod: PaymentMethod::fromString('self'),
            paidByUserId: null,
            paidAt: null,
            remark: null,
            cancelledAt: null,
            cancelReason: null,
            createdAt: new \DateTimeImmutable('2026-07-12 15:00:00'),
            updatedAt: null,
        );
    }

    #[Test]
    public function cancel_sets_cancelled_at_and_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::fromString(OrderStatus::PENDING_PAYMENT));
        $savedOrder = null;

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($order);
        $repository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Order $order) use (&$savedOrder) {
                $savedOrder = $order;

                return $order;
            });

        $handler = new CancelOrderHandler($repository, new OrderStateMachine());
        $handler->handle(1, '员工要求取消');

        $this->assertNotNull($savedOrder);
        $this->assertSame(OrderStatus::CANCELLED, $savedOrder->status->value);
        $this->assertNotNull($savedOrder->cancelledAt);
        $this->assertSame('员工要求取消', $savedOrder->cancelReason);
    }

    #[Test]
    public function mark_preparing_rejects_invalid_transition(): void
    {
        $order = $this->makeOrder(OrderStatus::fromString(OrderStatus::PENDING_PAYMENT));

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($order);
        $repository->expects($this->never())
            ->method('save');

        $handler = new MarkOrderPreparingHandler($repository, new OrderStateMachine());

        $this->expectException(InvalidOrderTransitionException::class);
        $handler->handle(1);
    }

    #[Test]
    public function mark_preparing_transitions_paid_to_preparing(): void
    {
        $order = $this->makeOrder(OrderStatus::fromString(OrderStatus::PAID));

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($order);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Order $o) => $o->status->value === OrderStatus::PREPARING))
            ->willReturnArgument(0);

        $handler = new MarkOrderPreparingHandler($repository, new OrderStateMachine());
        $updated = $handler->handle(1);

        $this->assertSame(OrderStatus::PREPARING, $updated->status->value);
    }

    #[Test]
    public function mark_ready_transitions_preparing_to_ready(): void
    {
        $order = $this->makeOrder(OrderStatus::fromString(OrderStatus::PREPARING));

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($order);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Order $o) => $o->status->value === OrderStatus::READY))
            ->willReturnArgument(0);

        $handler = new MarkOrderReadyHandler($repository, new OrderStateMachine());
        $updated = $handler->handle(1);

        $this->assertSame(OrderStatus::READY, $updated->status->value);
    }

    #[Test]
    public function complete_transitions_ready_to_completed(): void
    {
        $order = $this->makeOrder(OrderStatus::fromString(OrderStatus::READY));

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($order);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Order $o) => $o->status->value === OrderStatus::COMPLETED))
            ->willReturnArgument(0);

        $handler = new CompleteOrderHandler($repository, new OrderStateMachine());
        $updated = $handler->handle(1);

        $this->assertSame(OrderStatus::COMPLETED, $updated->status->value);
    }

    #[Test]
    public function handler_throws_when_order_not_found(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(99999)
            ->willReturn(null);
        $repository->expects($this->never())
            ->method('save');

        $handler = new MarkOrderPreparingHandler($repository, new OrderStateMachine());

        $this->expectException(OrderNotFoundException::class);
        $handler->handle(99999);
    }
}
