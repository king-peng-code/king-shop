<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Order\DTO\AdminOrderListQuery;
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
            ->with(['items', 'user', 'paidByUser'])
            ->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function searchAdmin(AdminOrderListQuery $query): array
    {
        $builder = OrderModel::query()
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->select(
                'orders.*',
                'users.name as user_name',
                'users.phone as user_phone',
                'users.department as user_department',
            )
            ->orderByDesc('orders.id');

        $this->applyAdminFilters($builder, $query);

        $paginator = $builder->paginate(
            $query->perPage,
            ['orders.*', 'users.name as user_name', 'users.phone as user_phone', 'users.department as user_department'],
            'page',
            $query->page,
        );

        return [
            'items' => $paginator->getCollection()
                ->map(fn (OrderModel $model) => $this->toDomain($model))
                ->all(),
            'total' => $paginator->total(),
        ];
    }

    public function save(Order $order): Order
    {
        $attributes = [
            'order_no' => $order->orderNo,
            'user_id' => $order->userId,
            'total_amount' => $order->totalAmount,
            'status' => $order->status->value,
            'payment_method' => $order->paymentMethod->value,
            'paid_by_user_id' => $order->paidByUserId,
            'paid_at' => $order->paidAt,
            'remark' => $order->remark,
            'cancelled_at' => $order->cancelledAt,
            'cancel_reason' => $order->cancelReason,
        ];

        if ($order->id === null) {
            $model = OrderModel::query()->create($attributes);
            $this->persistItems($model, $order->items);
        } else {
            $model = OrderModel::query()->findOrFail($order->id);
            $model->fill($attributes);
            $model->save();
        }

        $model = OrderModel::query()
            ->with(['items', 'user', 'paidByUser'])
            ->findOrFail($model->id);

        return $this->toDomain($model);
    }

    /**
     * @param  Builder<OrderModel>  $query
     */
    private function applyAdminFilters(Builder $query, AdminOrderListQuery $listQuery): void
    {
        if ($listQuery->status !== null && $listQuery->status !== '') {
            $query->where('orders.status', $listQuery->status);
        }

        if ($listQuery->userId !== null) {
            $query->where('orders.user_id', $listQuery->userId);
        }

        if ($listQuery->dateFrom !== null && $listQuery->dateFrom !== '') {
            $query->whereDate('orders.created_at', '>=', $listQuery->dateFrom);
        }

        if ($listQuery->dateTo !== null && $listQuery->dateTo !== '') {
            $query->whereDate('orders.created_at', '<=', $listQuery->dateTo);
        }

        if ($listQuery->keyword !== '') {
            $keyword = $listQuery->keyword;
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('orders.order_no', 'like', "%{$keyword}%")
                    ->orWhere('users.name', 'like', "%{$keyword}%")
                    ->orWhere('users.phone', 'like', "%{$keyword}%");
            });
        }
    }

    /**
     * @param  OrderItem[]  $items
     */
    private function persistItems(OrderModel $model, array $items): void
    {
        foreach ($items as $item) {
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
    }

    private function toDomain(OrderModel $model): Order
    {
        $userName = $model->user_name ?? $model->user?->name;
        $userPhone = $model->user_phone ?? $model->user?->phone;
        $userDepartment = $model->user_department ?? $model->user?->department;
        $paidByUserName = $model->paidByUser?->name;

        $items = $model->relationLoaded('items')
            ? $model->items
                ->map(fn (OrderItemModel $item) => $this->itemToDomain($item))
                ->all()
            : [];

        return new Order(
            id: $model->id,
            orderNo: $model->order_no,
            userId: $model->user_id,
            totalAmount: $model->total_amount,
            status: OrderStatus::fromString($model->status),
            paymentMethod: PaymentMethod::fromString($model->payment_method),
            paidByUserId: $model->paid_by_user_id,
            paidAt: $model->paid_at?->toDateTimeImmutable(),
            remark: $model->remark,
            cancelledAt: $model->cancelled_at?->toDateTimeImmutable(),
            cancelReason: $model->cancel_reason,
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable(),
            items: $items,
            userName: $userName,
            userPhone: $userPhone,
            userDepartment: $userDepartment,
            paidByUserName: $paidByUserName,
        );
    }

    private function itemToDomain(OrderItemModel $model): OrderItem
    {
        return new OrderItem(
            id: $model->id,
            productId: $model->product_id,
            productName: $model->product_name,
            productImage: $model->product_image,
            price: $model->price,
            quantity: $model->quantity,
            subtotal: $model->subtotal,
        );
    }
}
