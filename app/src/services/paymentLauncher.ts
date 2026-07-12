import * as WeChat from 'react-native-wechat-lib';
import {simulateFakeNotify} from '../api/orders';
import {WECHAT_APP_ID} from '../config/payment';
import type {PayParams, WechatPrepayParams} from '../types/order';

let wechatRegistered = false;

function ensureWechatRegistered(): void {
  if (wechatRegistered || !WECHAT_APP_ID) {
    return;
  }
  WeChat.registerApp(WECHAT_APP_ID, '');
  wechatRegistered = true;
}

export async function launchWechatPay(
  prepay: WechatPrepayParams,
): Promise<'success' | 'cancelled' | 'failed'> {
  if (!WECHAT_APP_ID) {
    throw new Error('未配置微信 AppID，请在 app/src/config/payment.ts 设置 WECHAT_APP_ID');
  }

  ensureWechatRegistered();

  const installed = await WeChat.isWXAppInstalled();
  if (!installed) {
    throw new Error('未安装微信客户端');
  }

  try {
    await WeChat.pay({
      partnerId: prepay.partnerid,
      prepayId: prepay.prepayid,
      nonceStr: prepay.noncestr,
      timeStamp: prepay.timestamp,
      package: prepay.package,
      sign: prepay.sign,
    });
    return 'success';
  } catch (error: unknown) {
    const code =
      typeof error === 'object' &&
      error !== null &&
      'code' in error &&
      typeof (error as {code: unknown}).code === 'number'
        ? (error as {code: number}).code
        : null;
    if (code === -2) {
      return 'cancelled';
    }
    return 'failed';
  }
}

export async function launchFakePay(outTradeNo: string): Promise<void> {
  await simulateFakeNotify(outTradeNo);
}

export function isAlipayParams(
  params: PayParams,
): params is Extract<PayParams, {channel: 'alipay_sandbox'}> {
  return params.channel === 'alipay_sandbox';
}

export function isFakeParams(
  params: PayParams,
): params is Extract<PayParams, {channel: 'fake'}> {
  return params.channel === 'fake';
}

export function isWechatParams(
  params: PayParams,
): params is Extract<PayParams, {channel: 'wechat'}> {
  return params.channel === 'wechat';
}
