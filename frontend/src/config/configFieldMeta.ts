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
  'order.share_title': { type: 'input' },
  'order.share_message': { type: 'textarea', rows: 5 },
  'order.share_copy_text': { type: 'textarea', rows: 3 },
  'payment.wechat.cert': { type: 'textarea', rows: 4 },
  'payment.alipay.private_key': { type: 'textarea', rows: 4 },
  'payment.alipay.public_key': { type: 'textarea', rows: 4 },
  'external_user.tag_presets': { type: 'textarea', rows: 3 },
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
  'wechat.app_id',
]);

const ALIPAY_KEYS = new Set([
  'alipay.app_id',
  'alipay.private_key',
  'alipay.public_key',
]);

const LOCAL_KEYS = new Set(['local.public_base_url']);

const OSS_KEYS = new Set([
  'oss.bucket',
  'oss.endpoint',
  'oss.public_base_url',
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
    if (driver === 'oss' && LOCAL_KEYS.has(key)) {
      return false;
    }
  }

  return true;
}

const GROUP_FIELD_ORDER: Record<string, string[]> = {
  order: [
    'auto_cancel_minutes',
    'share_title',
    'share_message',
    'share_copy_text',
  ],
  payment: [
    'provider',
    'alipay.app_id',
    'alipay.private_key',
    'alipay.public_key',
    'wechat.app_id',
    'wechat.mch_id',
    'wechat.api_key',
    'wechat.cert',
  ],
  storage: [
    'driver',
    'local.public_base_url',
    'oss.bucket',
    'oss.endpoint',
    'oss.public_base_url',
    'oss.access_key',
    'oss.secret_key',
  ],
};

export function getFieldExtra(group: string, key: string): string | undefined {
  if (group === 'storage' && key === 'local.public_base_url') {
    return '图片访问域名，填写后即时生效。本地示例：http://localhost:8000';
  }
  if (group === 'storage' && key === 'oss.public_base_url') {
    return '图片 CDN 或 OSS 绑定的公开访问域名，如 https://cdn.example.com';
  }
  return undefined;
}

export function sortConfigItems<T extends { key: string }>(
  group: string,
  items: T[],
): T[] {
  const order = GROUP_FIELD_ORDER[group];
  if (!order) {
    return items;
  }

  const rank = new Map(order.map((key, index) => [key, index]));
  return [...items].sort((a, b) => {
    const rankA = rank.get(a.key) ?? Number.MAX_SAFE_INTEGER;
    const rankB = rank.get(b.key) ?? Number.MAX_SAFE_INTEGER;
    return rankA - rankB;
  });
}
