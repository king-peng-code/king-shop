import {API_BASE_URL} from '../config/api';
import type {
  CreateOrderPayload,
  Order,
  PayChannel,
  PayOrderResult,
  ProxyPayLinkResult,
} from '../types/order';
import {apiRequest} from './client';

export async function createOrder(payload: CreateOrderPayload): Promise<Order> {
  return apiRequest<Order>('/orders', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function getOrder(orderId: number): Promise<Order> {
  return apiRequest<Order>(`/orders/${orderId}`);
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
