let _wechatAppId: string | null = null;

export function getWechatAppId(): string | null {
  return _wechatAppId;
}

export function setWechatAppId(appId: string): void {
  _wechatAppId = appId;
}
