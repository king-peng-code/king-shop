import {apiRequest} from './client';
import {buildQueryString} from '../utils/queryString';
import {API_BASE_URL} from '../config/api';
import type {
  CreateOrderPayload,
  Order,
  OrderStatus,
  PayChannel,
  PayOrderResult,
  PaymentChannelsResult,
  ProxyPayLinkResult,
} from '../types/order';

export interface ListOrdersParams {
  status?: OrderStatus;
  page?: number;
  per_page?: number;
}

export interface PaginatedOrders {
  items: Order[];
  meta: {total: number; page: number; per_page: number};
}

export async function createOrder(payload: CreateOrderPayload): Promise<Order> {
  return apiRequest<Order>('/orders', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function getOrder(orderId: number): Promise<Order> {
  return apiRequest<Order>(`/orders/${orderId}`);
}

export async function listOrders(
  params: ListOrdersParams = {},
): Promise<PaginatedOrders> {
  const qs = buildQueryString({
    status: params.status,
    page: params.page,
    per_page: params.per_page,
  });
  return apiRequest<PaginatedOrders>(`/orders${qs ? `?${qs}` : ''}`);
}

export async function cancelOrder(orderId: number): Promise<Order> {
  return apiRequest<Order>(`/orders/${orderId}/cancel`, {method: 'POST'});
}

export async function payOrder(
  orderId: number,
  channel: PayChannel,
): Promise<PayOrderResult> {
  return apiRequest<PayOrderResult>(`/orders/${orderId}/pay`, {
    method: 'POST',
    body: JSON.stringify({channel}),
  });
}

export async function getPaymentChannels(): Promise<PaymentChannelsResult> {
  return apiRequest<PaymentChannelsResult>('/payment-channels');
}

export async function generateProxyPayLink(
  orderId: number,
): Promise<ProxyPayLinkResult> {
  return apiRequest<ProxyPayLinkResult>(
    `/orders/${orderId}/proxy-pay-link`,
    {method: 'POST'},
  );
}

export async function simulateFakeNotify(outTradeNo: string): Promise<void> {
  const origin = API_BASE_URL.replace(/\/api\/v1\/?$/, '');
  const response = await fetch(`${origin}/api/v1/payments/notify/alipay`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      trade_status: 'TRADE_SUCCESS',
      out_trade_no: outTradeNo,
      trade_no: `FAKE_DEV_${Date.now()}`,
    }),
  });

  if (!response.ok) {
    throw new Error('模拟支付通知失败');
  }
}
