import { request } from './client';
import type { PaginatedResult } from '../types/api';
import type { Order, OrderListParams } from '../types/order';

function toQuery(params: OrderListParams): string {
  const q = new URLSearchParams();
  if (params.status) q.set('status', params.status);
  if (params.user_id) q.set('user_id', String(params.user_id));
  if (params.date_from) q.set('date_from', params.date_from);
  if (params.date_to) q.set('date_to', params.date_to);
  if (params.keyword) q.set('keyword', params.keyword);
  if (params.page) q.set('page', String(params.page));
  if (params.per_page) q.set('per_page', String(params.per_page));
  const s = q.toString();
  return s ? `?${s}` : '';
}

export const ordersApi = {
  list(params: OrderListParams = {}): Promise<PaginatedResult<Order>> {
    return request<PaginatedResult<Order>>(`/admin/orders${toQuery(params)}`);
  },

  get(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}`);
  },

  markPreparing(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/preparing`, { method: 'POST' });
  },

  markReady(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/ready`, { method: 'POST' });
  },

  complete(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/complete`, { method: 'POST' });
  },

  cancel(id: number, cancelReason?: string): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ cancel_reason: cancelReason ?? null }),
    });
  },
};
