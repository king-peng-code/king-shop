import type {ChannelOption} from '../components/PaymentChannelPicker';

export function selfPayChannels(): ChannelOption[] {
  const channels: ChannelOption[] = [
    {value: 'alipay_sandbox', label: '支付宝'},
    {value: 'wechat', label: '微信支付'},
  ];
  if (__DEV__) {
    channels.push({value: 'fake', label: '模拟支付（开发）'});
  }
  return channels;
}
