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
            throw new \LogicException('Order creation is not supported in admin repository.');
        }

        $model = OrderModel::query()->findOrFail($order->id);
        $model->fill([
            'status' => $order->status->value,
            'cancelled_at' => $order->cancelledAt?->format('Y-m-d H:i:s'),
            'cancel_reason' => $order->cancelReason,
        ]);
        $model->save();

        return $this->findById($model->id) ?? throw new \RuntimeException('Failed to reload order.');
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
