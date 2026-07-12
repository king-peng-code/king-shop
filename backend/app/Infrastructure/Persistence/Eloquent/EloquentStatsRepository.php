<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Statistics\Repositories\StatsRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentStatsRepository implements StatsRepositoryInterface
{
    private const int MAX_RESULTS = 200;

    private const array PAID_STATUSES = ['paid'];

    public function getEmployeeStats(?string $keyword = null): array
    {
        $subQuery = OrderModel::query()
            ->select('user_id', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total_amount) as total_amount'))
            ->whereIn('status', self::PAID_STATUSES)
            ->whereNotNull('paid_at')
            ->groupBy('user_id');

        return UserModel::query()
            ->where('role', 'employee')
            ->joinSub($subQuery, 'order_stats', function ($join): void {
                $join->on('users.id', '=', 'order_stats.user_id');
            })
            ->when($keyword !== null && $keyword !== '', fn (Builder $q) => $q->where(function (Builder $sub) use ($keyword): void {
                $sub->where('users.name', 'like', "%{$keyword}%")
                    ->orWhere('users.phone', 'like', "%{$keyword}%");
            }))
            ->orderByDesc('order_stats.order_count')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->id,
                'name' => $row->name,
                'phone' => $row->phone,
                'order_count' => (int) $row->order_count,
                'total_amount' => (int) ($row->total_amount ?? 0),
            ])
            ->all();
    }

    public function getProxyPayerStats(?string $keyword = null): array
    {
        $subQuery = OrderModel::query()
            ->select(
                'paid_by_external_user_id',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_amount'),
            )
            ->whereNotNull('paid_by_external_user_id')
            ->whereIn('status', self::PAID_STATUSES)
            ->whereNotNull('paid_at')
            ->groupBy('paid_by_external_user_id');

        return ExternalUserModel::query()
            ->joinSub($subQuery, 'order_stats', function ($join): void {
                $join->on('external_users.id', '=', 'order_stats.paid_by_external_user_id');
            })
            ->when($keyword !== null && $keyword !== '', fn (Builder $q) => $q->where(function (Builder $sub) use ($keyword): void {
                $sub->where('external_users.name', 'like', "%{$keyword}%")
                    ->orWhere('external_users.phone', 'like', "%{$keyword}%");
            }))
            ->orderByDesc('order_stats.order_count')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn ($row) => [
                'external_user_id' => (int) $row->id,
                'name' => $row->name,
                'phone' => $row->phone,
                'provider' => $row->provider,
                'order_count' => (int) $row->order_count,
                'total_amount' => (int) ($row->total_amount ?? 0),
            ])
            ->all();
    }
}
