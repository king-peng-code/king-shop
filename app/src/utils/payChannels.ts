import {getPaymentChannels} from '../api/orders';
import type {ChannelOption} from '../components/PaymentChannelPicker';
import type {PayChannel} from '../types/order';

export async function selfPayChannels(): Promise<ChannelOption[]> {
  try {
    const result = await getPaymentChannels();
    return result.self_pay.map(ch => ({
      value: ch.value as PayChannel,
      label: ch.label,
    }));
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
