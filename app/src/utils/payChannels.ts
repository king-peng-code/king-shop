import {getPaymentChannels} from '../api/orders';
import {setWechatAppId} from '../config/payment';
import type {ChannelOption} from '../components/PaymentChannelPicker';
import type {PayChannel} from '../types/order';

export async function selfPayChannels(): Promise<ChannelOption[]> {
  const result = await getPaymentChannels();

  if (result.wechat_app_id) {
    setWechatAppId(result.wechat_app_id);
  }

  return result.self_pay.map(ch => ({
    value: ch.value as PayChannel,
    label: ch.label,
  }));
}
