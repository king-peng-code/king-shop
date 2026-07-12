import { request } from './client';

export interface ProxyPayPreview {
  total_amount: number;
  status: string;
  buyer_name: string | null;
  brand_name: string;
  items_summary: string;
  expires_at: string;
  payable: boolean;
}

export interface ProxyPayLink {
  url: string;
  token: string;
  expires_at: string;
  share_title: string;
  share_message: string;
  share_copy_text: string;
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

  pay(
    token: string,
    payload: { channel?: string; provider: string; external_id?: string; payer_name?: string },
  ): Promise<ProxyPayInitResult> {
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
