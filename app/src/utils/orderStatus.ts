import type {OrderStatus} from '../types/order';

const LABELS: Record<OrderStatus, string> = {
  pending_payment: '待支付',
  paid: '已支付',
  cancelled: '已取消',
};

const COLORS: Record<OrderStatus, string> = {
  pending_payment: '#f57c00',
  paid: '#1976d2',
  cancelled: '#9e9e9e',
};

export function getOrderStatusLabel(
  status: OrderStatus,
  cancelReason?: string | null,
): string {
  if (status === 'cancelled' && cancelReason?.includes('超时')) {
    return '已取消(超时过期)';
  }

  return LABELS[status];
}

export function getOrderStatusColor(status: OrderStatus): string {
  return COLORS[status];
}
