<?php

namespace App\Application\Order\CreateOrder;

use App\Application\Order\DTO\CreateOrderCommand;
use App\Application\Order\DTO\CreateOrderItemCommand;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Entities\OrderItem;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\Services\OrderNumberGeneratorInterface;
use App\Domain\Order\ValueObjects\OrderStatus;

class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderNumberGeneratorInterface $orderNumberGenerator,
    ) {}

    public function handle(CreateOrderCommand $command): Order
    {
        if ($command->items === []) {
            throw new \InvalidArgumentException('订单商品不能为空');
        }

        $orderItems = [];
        $totalAmount = 0;

        foreach ($command->items as $item) {
            $orderItems[] = $this->buildOrderItem($item);
            $totalAmount += $orderItems[array_key_last($orderItems)]->subtotal;
        }

        $order = new Order(
            id: null,
            orderNo: $this->orderNumberGenerator->generate(),
            userId: $command->userId,
            totalAmount: $totalAmount,
            status: OrderStatus::fromString(OrderStatus::PENDING_PAYMENT),
            paymentMethod: $command->paymentMethod,
            paidByUserId: null,
            paidAt: null,
            remark: $command->remark,
            cancelledAt: null,
            cancelReason: null,
            createdAt: new \DateTimeImmutable,
            items: $orderItems,
        );

        return $this->orderRepository->save($order);
    }

    private function buildOrderItem(CreateOrderItemCommand $item): OrderItem
    {
        $product = $this->productRepository->findVisibleById($item->productId);

        if ($product === null) {
            throw new \App\Domain\Catalog\Exceptions\ProductNotFoundException('商品不存在或已下架');
        }

        $subtotal = $product->price * $item->quantity;

        return new OrderItem(
            id: null,
            productId: $product->id ?? throw new \RuntimeException('Product id missing'),
            productName: $product->name,
            productImage: $product->imagePath,
            price: $product->price,
            quantity: $item->quantity,
            subtotal: $subtotal,
        );
    }
}
