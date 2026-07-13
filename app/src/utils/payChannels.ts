import {getPaymentChannels} from '../api/orders';
import type {ChannelOption} from '../components/PaymentChannelPicker';
import type {PayChannel} from '../types/order';

let cachedChannels: ChannelOption[] | null = null;

export async function selfPayChannels(): Promise<ChannelOption[]> {
  if (cachedChannels) {
    return cachedChannels;
  }

  try {
    const result = await getPaymentChannels();
    cachedChannels = result.self_pay.map(ch => ({
      value: ch.value as PayChannel,
      label: ch.label,
    }));
    return cachedChannels;
  } catch {
    // Fallback: in dev, show all channels; in prod, show both
    if (__DEV__) {
      return [
        {value: 'alipay_sandbox' as PayChannel, label: '支付宝'},
        {value: 'wechat' as PayChannel, label: '微信支付'},
        {value: 'fake' as PayChannel, label: '模拟支付（开发）'},
      ];
    }
    return [
      {value: 'alipay_sandbox' as PayChannel, label: '支付宝'},
      {value: 'wechat' as PayChannel, label: '微信支付'},
    ];
  }
}

/** Clear cached channels (e.g., after config change) */
export function clearPaymentChannelCache(): void {
  cachedChannels = null;
}
