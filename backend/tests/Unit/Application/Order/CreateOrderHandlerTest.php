<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\CreateOrder\CreateOrderHandler;
use App\Application\Order\DTO\CreateOrderCommand;
use App\Application\Order\DTO\CreateOrderItemCommand;
use App\Domain\Catalog\Exceptions\ProductNotFoundException;
use App\Domain\Order\ValueObjects\PaymentMethod;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateOrderHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_order_snapshots_product_and_calculates_total(): void
    {
        $user = UserModel::factory()->create();
        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->onSale()->create([
            'category_id' => $category->id,
            'name' => '拿铁',
            'price' => 1500,
            'image_path' => 'uploads/2026/07/latte.jpg',
        ]);

        $order = app(CreateOrderHandler::class)->handle(
            new CreateOrderCommand(
                userId: $user->id,
                items: [new CreateOrderItemCommand($product->id, 2)],
                paymentMethod: PaymentMethod::fromString('self'),
                remark: '少糖',
            ),
        );

        $this->assertMatchesRegularExpression('/^KS\d{15}$/', $order->orderNo);
        $this->assertSame('pending_payment', $order->status->value);
        $this->assertSame(3000, $order->totalAmount);
        $this->assertCount(1, $order->items);
        $this->assertSame('拿铁', $order->items[0]->productName);
        $this->assertSame('uploads/2026/07/latte.jpg', $order->items[0]->productImage);
        $this->assertSame('少糖', $order->remark);
    }

    #[Test]
    public function create_order_rejects_off_sale_product(): void
    {
        $user = UserModel::factory()->create();
        $product = ProductModel::factory()->offSale()->create();

        $this->expectException(ProductNotFoundException::class);

        app(CreateOrderHandler::class)->handle(
            new CreateOrderCommand(
                userId: $user->id,
                items: [new CreateOrderItemCommand($product->id, 1)],
                paymentMethod: PaymentMethod::fromString('self'),
                remark: null,
            ),
        );
    }
}
