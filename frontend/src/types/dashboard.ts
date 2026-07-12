export interface DashboardSummaryPeriod {
  order_count: number;
  paid_order_count: number;
  sales_amount: number;
}

export interface StatusDistributionItem {
  status: string;
  label: string;
  count: number;
}

export interface HotProductItem {
  product_id: number;
  product_name: string;
  quantity: number;
  sales_amount: number;
}

export interface DailySalesItem {
  date: string;
  sales_amount: number;
  order_count: number;
}

export interface DashboardStats {
  summary: {
    today: DashboardSummaryPeriod;
    week: DashboardSummaryPeriod;
  };
  status_distribution: StatusDistributionItem[];
  hot_products_by_quantity: HotProductItem[];
  hot_products_by_sales: HotProductItem[];
  week_daily_sales: DailySalesItem[];
}
