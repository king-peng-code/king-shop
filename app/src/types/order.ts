export type PaymentMethod = 'self' | 'proxy';

export type PayChannel = 'fake' | 'alipay_sandbox' | 'wechat';

export interface PayChannelOption {
  value: PayChannel;
  label: string;
}

export interface PaymentChannelsResult {
  self_pay: PayChannelOption[];
  proxy_pay: PayChannelOption[];
  wechat_app_id?: string;
}

export type OrderStatus = 'pending_payment' | 'paid' | 'cancelled';

export interface OrderItem {
  id: number;
  product_id: number;
  product_name: string;
  product_image: string | null;
  image_url: string | null;
  price: number;
  quantity: number;
  subtotal: number;
}

export interface Order {
  id: number;
  order_no: string;
  total_amount: number;
  status: OrderStatus;
  payment_method: PaymentMethod;
  paid_at: string | null;
  remark: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
  created_at: string;
  items?: OrderItem[];
  paid_by_payer?: {id: number; name: string; provider?: string};
}

export interface CreateOrderPayload {
  items: Array<{product_id: number; quantity: number}>;
  payment_method: PaymentMethod;
  remark?: string;
}

export interface WechatPrepayParams {
  appid: string;
  partnerid: string;
  prepayid: string;
  package: string;
  noncestr: string;
  timestamp: string;
  sign: string;
}

export type PayParams =
  | {
      channel: 'fake';
      trade_type?: string;
      out_trade_no: string;
      amount: number;
      order_no: string;
    }
  | {channel: 'alipay_sandbox'; pay_url: string}
  | {channel: 'wechat'; prepay: WechatPrepayParams};

export interface PayOrderResult {
  payment: {
    id: number;
    order_id: number;
    out_trade_no: string;
    amount: number;
    channel: PayChannel;
    status: string;
  };
  pay_params: PayParams;
}

export interface ProxyPayLinkResult {
  url: string;
  token: string;
  expires_at: string;
  share_title: string;
  share_message: string;
  share_copy_text: string;
}

export type PaymentOutcome = 'success' | 'failed' | 'pending';
