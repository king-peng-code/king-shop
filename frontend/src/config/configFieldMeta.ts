// frontend/src/config/configFieldMeta.ts
export type FieldType = 'input' | 'password' | 'textarea' | 'select' | 'number';

export interface FieldMeta {
  type: FieldType;
  options?: { label: string; value: string }[];
  min?: number;
  max?: number;
  rows?: number;
}

const META_OVERRIDES: Record<string, FieldMeta> = {
  'payment.provider': {
    type: 'select',
    options: [
      { label: '支付宝沙箱', value: 'alipay_sandbox' },
      { label: '微信支付', value: 'wechat' },
    ],
  },
  'storage.driver': {
    type: 'select',
    options: [
      { label: '本地存储', value: 'local' },
      { label: '阿里云 OSS', value: 'oss' },
    ],
  },
  'order.auto_cancel_minutes': {
    type: 'number',
    min: 1,
    max: 1440,
  },
  'payment.wechat.cert': { type: 'textarea', rows: 4 },
  'payment.alipay.private_key': { type: 'textarea', rows: 4 },
};

export function fieldKey(group: string, key: string): string {
  return `${group}.${key}`;
}

export function getFieldMeta(
  group: string,
  key: string,
  isSensitive: boolean,
): FieldMeta {
  const override = META_OVERRIDES[fieldKey(group, key)];
  if (override) {
    return override;
  }
  if (isSensitive) {
    return { type: 'password' };
  }
  return { type: 'input' };
}

const WECHAT_KEYS = new Set([
  'wechat.mch_id',
  'wechat.api_key',
  'wechat.cert',
]);

const ALIPAY_KEYS = new Set([
  'alipay.app_id',
  'alipay.private_key',
]);

const OSS_KEYS = new Set([
  'oss.bucket',
  'oss.endpoint',
  'oss.access_key',
  'oss.secret_key',
]);

export function isFieldVisible(
  group: string,
  key: string,
  values: Record<string, string>,
): boolean {
  if (group === 'payment') {
    const provider = values['payment.provider'] ?? 'alipay_sandbox';
    if (provider === 'alipay_sandbox' && WECHAT_KEYS.has(key)) {
      return false;
    }
    if (provider === 'wechat' && ALIPAY_KEYS.has(key)) {
      return false;
    }
  }

  if (group === 'storage') {
    const driver = values['storage.driver'] ?? 'local';
    if (driver === 'local' && OSS_KEYS.has(key)) {
      return false;
    }
  }

  return true;
}
