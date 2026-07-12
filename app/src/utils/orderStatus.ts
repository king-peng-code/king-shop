import type {OrderStatus} from '../types/order';

const LABELS: Record<OrderStatus, string> = {
  pending_payment: '待支付',
  paid: '已支付',
  preparing: '备餐中',
  ready: '可取餐',
  completed: '已完成',
  cancelled: '已取消',
};

const COLORS: Record<OrderStatus, string> = {
  pending_payment: '#f57c00',
  paid: '#1976d2',
  preparing: '#7b1fa2',
  ready: '#2e7d32',
  completed: '#616161',
  cancelled: '#9e9e9e',
};

export function getOrderStatusLabel(status: OrderStatus): string {
  return LABELS[status];
}

export function getOrderStatusColor(status: OrderStatus): string {
  return COLORS[status];
}
