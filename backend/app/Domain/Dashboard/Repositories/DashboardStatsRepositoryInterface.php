<?php

namespace App\Domain\Dashboard\Repositories;

interface DashboardStatsRepositoryInterface
{
    /**
     * @return array{
     *   summary: array{
     *     today: array{order_count: int, paid_order_count: int, sales_amount: int},
     *     week: array{order_count: int, paid_order_count: int, sales_amount: int}
     *   },
     *   status_distribution: list<array{status: string, label: string, count: int}>,
     *   hot_products_by_quantity: list<array{product_id: int, product_name: string, quantity: int, sales_amount: int}>,
     *   hot_products_by_sales: list<array{product_id: int, product_name: string, quantity: int, sales_amount: int}>,
     *   week_daily_sales: list<array{date: string, sales_amount: int, order_count: int}>
     * }
     */
    public function getStats(): array;
}
