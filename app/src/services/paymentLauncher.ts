import * as WeChat from 'react-native-wechat-lib';
import {simulateFakeNotify} from '../api/orders';
import {getWechatAppId} from '../config/payment';
import type {PayParams, WechatPrepayParams} from '../types/order';

export interface WechatShareOptions {
  title: string;
  description: string;
  webpageUrl: string;
  /** 0 = 聊天会话, 1 = 朋友圈, 2 = 收藏 */
  scene?: 0 | 1 | 2;
}

let wechatRegistered = false;

function ensureWechatRegistered(): void {
  if (wechatRegistered) {
    return;
  }
  const appId = getWechatAppId();
  if (!appId) {
    return;
  }
  WeChat.registerApp(appId, '');
  wechatRegistered = true;
}

export async function launchWechatPay(
  prepay: WechatPrepayParams,
): Promise<'success' | 'cancelled' | 'failed'> {
  const appId = getWechatAppId();
  if (!appId) {
    throw new Error('未获取到微信 AppID，请检查支付渠道配置');
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

/**
 * 将网页链接分享到微信聊天会话。
 * 未配置 WECHAT_APP_ID 或未安装微信时返回 false，由调用方降级。
 */
export async function shareToWechat(
  options: WechatShareOptions,
): Promise<'shared' | 'cancelled' | 'unavailable'> {
  const appId = getWechatAppId();
  if (!appId) {
    return 'unavailable';
  }

  ensureWechatRegistered();

  const installed = await WeChat.isWXAppInstalled();
  if (!installed) {
    return 'unavailable';
  }

  try {
    await WeChat.shareWebpage({
      title: options.title,
      description: options.description,
      webpageUrl: options.webpageUrl,
      scene: options.scene ?? 0,
    });
    return 'shared';
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
    return 'unavailable';
  }
}
