<?php

namespace Tests\Feature\Infrastructure;

use App\Application\Order\DTO\AdminOrderListQuery;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
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
}
