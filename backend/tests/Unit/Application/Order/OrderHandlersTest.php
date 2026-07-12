<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\CancelOrder\CancelOrderHandler;
use App\Application\Order\DTO\AdminOrderListQuery;
use App\Application\Order\GetAdminOrder\GetAdminOrderHandler;
use App\Application\Order\ListAdminOrders\ListAdminOrdersHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderHandlersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function list_admin_orders_returns_meta(): void
    {
        OrderModel::factory()->count(2)->paid()->create();

        $result = app(ListAdminOrdersHandler::class)->handle(
            new AdminOrderListQuery(null, null, null, null, '', 1, 20),
        );

        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['meta']['total']);
    }

    #[Test]
    public function get_admin_order_throws_when_not_found(): void
    {
        $this->expectException(OrderNotFoundException::class);
        app(GetAdminOrderHandler::class)->handle(99999);
    }

    #[Test]
    public function cancel_sets_cancelled_at_and_reason(): void
    {
        $order = OrderModel::factory()->create(['status' => 'pending_payment']);

        $updated = app(CancelOrderHandler::class)->handle($order->id, '员工要求取消');

        $this->assertSame('cancelled', $updated->status->value);
        $this->assertNotNull($updated->cancelledAt);
        $this->assertSame('员工要求取消', $updated->cancelReason);
    }

    #[Test]
    public function list_handler_delegates_to_repository(): void
    {
        $order = new Order(
            id: 1,
            orderNo: 'KS001',
            userId: 1,
            totalAmount: 1000,
            status: OrderStatus::fromString('paid'),
            paymentMethod: PaymentMethod::fromString('self'),
            paidByUserId: null,
            paidAt: null,
            remark: null,
            cancelledAt: null,
            cancelReason: null,
            createdAt: new \DateTimeImmutable(),
        );

        $mock = $this->createMock(OrderRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('searchAdmin')
            ->willReturn(['items' => [$order], 'total' => 1]);

        $handler = new ListAdminOrdersHandler($mock);
        $result = $handler->handle(new AdminOrderListQuery(null, null, null, null, '', 1, 20));

        $this->assertCount(1, $result['items']);
        $this->assertSame(1, $result['meta']['total']);
    }
}
