export interface EmployeeStatsItem {
  user_id: number;
  name: string;
  phone: string;
  order_count: number;
  total_amount: number;
}

export interface ProxyPayerStatsItem {
  external_user_id: number;
  name: string | null;
  phone: string | null;
  provider: string;
  order_count: number;
  total_amount: number;
}
