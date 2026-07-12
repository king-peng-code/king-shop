<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Order\DTO\AdminOrderListQuery;
use App\Application\Order\DTO\UserOrderListQuery;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Entities\OrderItem;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\ValueObjects\OrderStatus;
use App\Domain\Order\ValueObjects\PaymentMethod;
use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use Illuminate\Database\Eloquent\Builder;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        $model = OrderModel::query()
            ->with(['user', 'paidByUser', 'items'])
            ->find($id);

        return $model ? $this->toDomain($model, includeItems: true) : null;
    }

    public function searchAdmin(AdminOrderListQuery $query): array
    {
        $builder = OrderModel::query()
            ->with(['user', 'paidByUser'])
            ->orderByDesc('orders.id');

        $this->applyAdminFilters($builder, $query);

        $paginator = $builder->paginate($query->perPage, ['orders.*'], 'page', $query->page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (OrderModel $model) => $this->toDomain($model, includeItems: false))
                ->all(),
            'total' => $paginator->total(),
        ];
    }

    public function save(Order $order): Order
    {
        if ($order->id === null) {
            return $this->create($order);
        }

        $model = OrderModel::query()->findOrFail($order->id);
        $model->fill([
            'status' => $order->status->value,
            'paid_at' => $order->paidAt?->format('Y-m-d H:i:s'),
            'paid_by_user_id' => $order->paidByUserId,
            'cancelled_at' => $order->cancelledAt?->format('Y-m-d H:i:s'),
            'cancel_reason' => $order->cancelReason,
        ]);
        $model->save();

        return $this->findById($model->id) ?? throw new \RuntimeException('Failed to reload order.');
    }

    public function searchUser(UserOrderListQuery $query): array
    {
        $builder = OrderModel::query()
            ->where('user_id', $query->userId)
            ->orderByDesc('orders.id');

        if ($query->status !== null && $query->status !== '') {
            $builder->where('status', $query->status);
        }

        $paginator = $builder->paginate($query->perPage, ['orders.*'], 'page', $query->page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (OrderModel $model) => $this->toDomain($model, includeItems: false))
                ->all(),
            'total' => $paginator->total(),
        ];
    }

    public function findExpiredPendingPayment(int $minutes): array
    {
        $cutoff = now()->subMinutes($minutes);

        return OrderModel::query()
            ->where('status', OrderStatus::PENDING_PAYMENT)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->get()
            ->map(fn (OrderModel $model) => $this->toDomain($model, includeItems: false))
            ->all();
    }

    private function create(Order $order): Order
    {
        $model = OrderModel::query()->create([
            'order_no' => $order->orderNo,
            'user_id' => $order->userId,
            'total_amount' => $order->totalAmount,
            'status' => $order->status->value,
            'payment_method' => $order->paymentMethod->value,
            'paid_by_user_id' => $order->paidByUserId,
            'paid_at' => $order->paidAt?->format('Y-m-d H:i:s'),
            'remark' => $order->remark,
            'cancelled_at' => $order->cancelledAt?->format('Y-m-d H:i:s'),
            'cancel_reason' => $order->cancelReason,
        ]);

        foreach ($order->items as $item) {
            OrderItemModel::query()->create([
                'order_id' => $model->id,
                'product_id' => $item->productId,
                'product_name' => $item->productName,
                'product_image' => $item->productImage,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ]);
        }

        return $this->findById($model->id) ?? throw new \RuntimeException('Failed to reload created order.');
    }

    /**
     * @param  Builder<OrderModel>  $query
     */
    private function applyAdminFilters(Builder $query, AdminOrderListQuery $filters): void
    {
        if ($filters->status !== null && $filters->status !== '') {
            $query->where('orders.status', $filters->status);
        }

        if ($filters->userId !== null) {
            $query->where('orders.user_id', $filters->userId);
        }

        if ($filters->dateFrom !== null && $filters->dateFrom !== '') {
            $query->whereDate('orders.created_at', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null && $filters->dateTo !== '') {
            $query->whereDate('orders.created_at', '<=', $filters->dateTo);
        }

        if ($filters->keyword !== '') {
            $keyword = $filters->keyword;
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('orders.order_no', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($keyword): void {
                        $userQuery->where('name', 'like', "%{$keyword}%")
                            ->orWhere('phone', 'like', "%{$keyword}%");
                    });
            });
        }
    }

    private function toDomain(OrderModel $model, bool $includeItems): Order
    {
        $items = [];

        if ($includeItems) {
            $items = $model->items
                ->map(fn (OrderItemModel $item) => new OrderItem(
                    id: $item->id,
                    productId: $item->product_id,
                    productName: $item->product_name,
                    productImage: $item->product_image,
                    price: $item->price,
                    quantity: $item->quantity,
                    subtotal: $item->subtotal,
                ))
                ->all();
        }

        return new Order(
            id: $model->id,
            orderNo: $model->order_no,
            userId: $model->user_id,
            totalAmount: $model->total_amount,
            status: OrderStatus::fromString($model->status),
            paymentMethod: PaymentMethod::fromString($model->payment_method),
            paidByUserId: $model->paid_by_user_id,
            paidAt: $model->paid_at ? \DateTimeImmutable::createFromMutable($model->paid_at) : null,
            remark: $model->remark,
            cancelledAt: $model->cancelled_at ? \DateTimeImmutable::createFromMutable($model->cancelled_at) : null,
            cancelReason: $model->cancel_reason,
            createdAt: \DateTimeImmutable::createFromMutable($model->created_at),
            items: $items,
            userName: $model->user?->name,
            userPhone: $model->user?->phone,
            userDepartment: $model->user?->department,
            paidByUserName: $model->paidByUser?->name,
            paidByUserPhone: $model->paidByUser?->phone,
            paidByUserDepartment: $model->paidByUser?->department,
        );
    }
}
