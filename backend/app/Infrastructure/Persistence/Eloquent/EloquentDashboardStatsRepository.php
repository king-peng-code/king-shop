<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class EloquentDashboardStatsRepository implements DashboardStatsRepositoryInterface
{
    /**
     * Boundaries use Asia/Shanghai wall-clock Carbon values to match how orders
     * persist paid_at / created_at in this codebase (local datetime strings).
     */
    private const PAID_STATUSES = ['paid'];

    private const STATUS_LABELS = [
        'pending_payment' => '待支付',
        'paid' => '已支付',
        'cancelled' => '已取消',
    ];

    public function getStats(): array
    {
        $now = $this->now();
        $todayStart = $this->todayStart();
        $weekStart = $this->weekStart();

        return [
            'summary' => [
                'today' => $this->buildSummary($todayStart, $now),
                'week' => $this->buildSummary($weekStart, $now),
            ],
            'status_distribution' => $this->buildStatusDistribution(),
            'hot_products_by_quantity' => $this->buildHotProductsByQuantity($weekStart, $now),
            'hot_products_by_sales' => $this->buildHotProductsBySales($weekStart, $now),
            'week_daily_sales' => $this->buildWeekDailySales($weekStart),
        ];
    }

    /**
     * @return array{order_count: int, paid_order_count: int, sales_amount: int}
     */
    private function buildSummary(Carbon $start, Carbon $end): array
    {
        $orderCount = OrderModel::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $paidStats = $this->paidOrdersQuery()
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('COUNT(*) as paid_order_count, COALESCE(SUM(total_amount), 0) as sales_amount')
            ->first();

        return [
            'order_count' => (int) $orderCount,
            'paid_order_count' => (int) ($paidStats->paid_order_count ?? 0),
            'sales_amount' => (int) ($paidStats->sales_amount ?? 0),
        ];
    }

    /**
     * @return list<array{status: string, label: string, count: int}>
     */
    private function buildStatusDistribution(): array
    {
        return OrderModel::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'label' => self::STATUS_LABELS[$row->status] ?? $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{product_id: int, product_name: string, quantity: int, sales_amount: int}>
     */
    private function buildHotProductsByQuantity(Carbon $weekStart, Carbon $now): array
    {
        return $this->hotProductsBaseQuery($weekStart, $now)
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'quantity' => (int) $row->quantity,
                'sales_amount' => (int) $row->sales_amount,
            ])
            ->all();
    }

    /**
     * @return list<array{product_id: int, product_name: string, quantity: int, sales_amount: int}>
     */
    private function buildHotProductsBySales(Carbon $weekStart, Carbon $now): array
    {
        return $this->hotProductsBaseQuery($weekStart, $now)
            ->orderByDesc('sales_amount')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'quantity' => (int) $row->quantity,
                'sales_amount' => (int) $row->sales_amount,
            ])
            ->all();
    }

    /**
     * @return list<array{date: string, sales_amount: int, order_count: int}>
     */
    private function buildWeekDailySales(Carbon $weekStart): array
    {
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $stats = $this->paidOrdersQuery()
                ->whereBetween('paid_at', [$dayStart, $dayEnd])
                ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as sales_amount')
                ->first();

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'sales_amount' => (int) ($stats->sales_amount ?? 0),
                'order_count' => (int) ($stats->order_count ?? 0),
            ];
        }

        return $days;
    }

    private function hotProductsBaseQuery(Carbon $weekStart, Carbon $now): Builder
    {
        return OrderItemModel::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', self::PAID_STATUSES)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$weekStart, $now])
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as quantity, SUM(order_items.subtotal) as sales_amount')
            ->groupBy('order_items.product_id', 'order_items.product_name');
    }

    /**
     * @return Builder<OrderModel>
     */
    private function paidOrdersQuery(): Builder
    {
        return OrderModel::query()
            ->whereIn('status', self::PAID_STATUSES)
            ->whereNotNull('paid_at');
    }

    private function now(): Carbon
    {
        return Carbon::now('Asia/Shanghai');
    }

    private function todayStart(): Carbon
    {
        return $this->now()->copy()->startOfDay();
    }

    private function weekStart(): Carbon
    {
        return $this->now()->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
