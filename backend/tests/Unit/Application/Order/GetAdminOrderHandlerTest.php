<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\GetAdminOrder\GetAdminOrderHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetAdminOrderHandlerTest extends TestCase
{
    #[Test]
    public function handle_returns_order_when_found(): void
    {
        $order = new Order(
            id: 42,
            orderNo: 'KS202607121430042',
            userId: 5,
            totalAmount: 1500,
            status: OrderStatus::fromString('pending_payment'),
            paymentMethod: PaymentMethod::fromString('self'),
            paidByExternalUserId: null,
            paidAt: null,
            remark: '少糖',
            cancelledAt: null,
            cancelReason: null,
            createdAt: new \DateTimeImmutable('2026-07-12 15:00:00'),
        );

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(42)
            ->willReturn($order);

        $handler = new GetAdminOrderHandler($repository);

        $this->assertSame($order, $handler->handle(42));
    }

    #[Test]
    public function handle_throws_when_order_not_found(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(99999)
            ->willReturn(null);

        $handler = new GetAdminOrderHandler($repository);

        $this->expectException(OrderNotFoundException::class);
        $handler->handle(99999);
    }
}
