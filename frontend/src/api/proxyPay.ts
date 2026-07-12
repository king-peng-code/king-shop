import { request } from './client';

export interface ProxyPayPreview {
  order_no: string;
  total_amount: number;
  status: string;
  buyer_name: string | null;
  expires_at: string;
  payable: boolean;
}

export interface ProxyPayLink {
  url: string;
  token: string;
  expires_at: string;
}

export interface ProxyPayInitResult {
  payment: {
    out_trade_no: string;
    status: string;
    channel: string;
  };
  pay_params: Record<string, unknown>;
}

export const proxyPayApi = {
  preview(token: string): Promise<ProxyPayPreview> {
    return request<ProxyPayPreview>(`/proxy-pay/${token}`);
  },

  pay(token: string, payload: { channel?: string; openid?: string } = {}): Promise<ProxyPayInitResult> {
    return request<ProxyPayInitResult>(`/proxy-pay/${token}/pay`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};

export const ordersApiExtension = {
  createProxyPayLink(orderId: number): Promise<ProxyPayLink> {
    return request<ProxyPayLink>(`/orders/${orderId}/proxy-pay-link`, { method: 'POST' });
  },
};
