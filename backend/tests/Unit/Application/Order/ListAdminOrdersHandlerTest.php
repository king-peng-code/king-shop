<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\DTO\AdminOrderListQuery;
use App\Application\Order\ListAdminOrders\ListAdminOrdersHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListAdminOrdersHandlerTest extends TestCase
{
    #[Test]
    public function handle_returns_items_and_meta_from_repository(): void
    {
        $order = new Order(
            id: 1,
            orderNo: 'KS202607121430001',
            userId: 10,
            totalAmount: 3000,
            status: OrderStatus::fromString('paid'),
            paymentMethod: PaymentMethod::fromString('self'),
            paidByUserId: null,
            paidAt: null,
            remark: null,
            cancelledAt: null,
            cancelReason: null,
            createdAt: new \DateTimeImmutable('2026-07-12 14:30:00'),
            updatedAt: null,
        );

        $query = new AdminOrderListQuery(
            status: 'paid',
            userId: null,
            dateFrom: null,
            dateTo: null,
            keyword: '',
            page: 2,
            perPage: 10,
        );

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('searchAdmin')
            ->with($query)
            ->willReturn(['items' => [$order], 'total' => 15]);

        $handler = new ListAdminOrdersHandler($repository);
        $result = $handler->handle($query);

        $this->assertCount(1, $result['items']);
        $this->assertSame($order, $result['items'][0]);
        $this->assertSame(15, $result['meta']['total']);
        $this->assertSame(2, $result['meta']['page']);
        $this->assertSame(10, $result['meta']['per_page']);
    }
}
