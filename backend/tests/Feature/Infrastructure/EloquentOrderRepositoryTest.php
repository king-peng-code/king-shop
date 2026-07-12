<?php

namespace Tests\Feature\Infrastructure;

use App\Application\Order\DTO\AdminOrderListQuery;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentOrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_admin_filters_by_status(): void
    {
        OrderModel::factory()->paid()->create();
        OrderModel::factory()->create(['status' => 'cancelled']);

        $repo = app(OrderRepositoryInterface::class);
        $result = $repo->searchAdmin(new AdminOrderListQuery(
            status: 'paid',
            userId: null,
            dateFrom: null,
            dateTo: null,
            keyword: '',
            page: 1,
            perPage: 20,
        ));

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('paid', $result['items'][0]->status->value);
    }

    #[Test]
    public function find_by_id_loads_items_and_user_names(): void
    {
        $user = UserModel::factory()->create(['name' => '张三']);
        $order = OrderModel::factory()->for($user, 'user')->paid()->create();
        OrderItemModel::factory()->for($order, 'order')->create(['product_name' => '拿铁']);

        $repo = app(OrderRepositoryInterface::class);
        $found = $repo->findById($order->id);

        $this->assertNotNull($found);
        $this->assertSame('张三', $found->userName);
        $this->assertCount(1, $found->items);
        $this->assertSame('拿铁', $found->items[0]->productName);
    }

    #[Test]
    public function search_admin_filters_by_keyword_on_order_no(): void
    {
        OrderModel::factory()->create(['order_no' => 'KS202607121430001']);
        OrderModel::factory()->create(['order_no' => 'KS202607121430002']);

        $result = app(OrderRepositoryInterface::class)->searchAdmin(new AdminOrderListQuery(
            status: null,
            userId: null,
            dateFrom: null,
            dateTo: null,
            keyword: '1430001',
            page: 1,
            perPage: 20,
        ));

        $this->assertSame(1, $result['total']);
        $this->assertSame('KS202607121430001', $result['items'][0]->orderNo);
    }

    #[Test]
    public function search_admin_filters_by_keyword_on_user_name(): void
    {
        $user = UserModel::factory()->create(['name' => '王五']);
        OrderModel::factory()->for($user, 'user')->create();
        OrderModel::factory()->create();

        $result = app(OrderRepositoryInterface::class)->searchAdmin(new AdminOrderListQuery(
            status: null,
            userId: null,
            dateFrom: null,
            dateTo: null,
            keyword: '王五',
            page: 1,
            perPage: 20,
        ));

        $this->assertSame(1, $result['total']);
        $this->assertSame('王五', $result['items'][0]->userName);
    }

    #[Test]
    public function search_admin_filters_by_user_id(): void
    {
        $user = UserModel::factory()->create();
        OrderModel::factory()->for($user, 'user')->create();
        OrderModel::factory()->create();

        $result = app(OrderRepositoryInterface::class)->searchAdmin(new AdminOrderListQuery(
            status: null,
            userId: $user->id,
            dateFrom: null,
            dateTo: null,
            keyword: '',
            page: 1,
            perPage: 20,
        ));

        $this->assertSame(1, $result['total']);
        $this->assertSame($user->id, $result['items'][0]->userId);
    }

    #[Test]
    public function find_by_id_includes_paid_by_user_name_for_proxy_order(): void
    {
        $payer = UserModel::factory()->create(['name' => '李四']);
        $order = OrderModel::factory()->proxy()->create([
            'paid_by_user_id' => $payer->id,
        ]);

        $found = app(OrderRepositoryInterface::class)->findById($order->id);

        $this->assertNotNull($found);
        $this->assertSame('proxy', $found->paymentMethod->value);
        $this->assertSame('李四', $found->paidByUserName);
    }

    #[Test]
    public function save_updates_existing_order_status(): void
    {
        $order = OrderModel::factory()->paid()->create();
        $domain = app(OrderRepositoryInterface::class)->findById($order->id);
        $this->assertNotNull($domain);

        $updated = new \App\Domain\Order\Entities\Order(
            id: $domain->id,
            orderNo: $domain->orderNo,
            userId: $domain->userId,
            totalAmount: $domain->totalAmount,
            status: OrderStatus::fromString('preparing'),
            paymentMethod: $domain->paymentMethod,
            paidByUserId: $domain->paidByUserId,
            paidAt: $domain->paidAt,
            remark: $domain->remark,
            cancelledAt: $domain->cancelledAt,
            cancelReason: $domain->cancelReason,
            createdAt: $domain->createdAt,
            updatedAt: $domain->updatedAt,
            items: $domain->items,
            userName: $domain->userName,
            userPhone: $domain->userPhone,
            userDepartment: $domain->userDepartment,
            paidByUserName: $domain->paidByUserName,
        );

        $saved = app(OrderRepositoryInterface::class)->save($updated);

        $this->assertSame('preparing', $saved->status->value);
        $this->assertSame('preparing', OrderModel::query()->find($order->id)->status);
    }
}
