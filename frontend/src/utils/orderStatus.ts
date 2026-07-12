export type OrderStatus = 'pending_payment' | 'paid' | 'cancelled';

const STATUS_LABELS: Record<OrderStatus, string> = {
  pending_payment: '待支付',
  paid: '已支付',
  cancelled: '已取消',
};

const STATUS_COLORS: Record<OrderStatus, string> = {
  pending_payment: 'orange',
  paid: 'blue',
  cancelled: 'red',
};

export function formatOrderStatusLabel(
  status: OrderStatus,
  cancelReason?: string | null,
): string {
  if (status === 'cancelled' && cancelReason?.includes('超时')) {
    return '已取消(超时过期)';
  }

  return STATUS_LABELS[status];
}

export function getOrderStatusColor(status: OrderStatus): string {
  return STATUS_COLORS[status];
}
