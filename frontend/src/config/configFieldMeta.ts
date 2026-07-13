// frontend/src/config/configFieldMeta.ts
export type FieldType = 'input' | 'password' | 'textarea' | 'select' | 'number' | 'switch';

export interface FieldMeta {
  type: FieldType;
  options?: { label: string; value: string }[];
  min?: number;
  max?: number;
  rows?: number;
}

const META_OVERRIDES: Record<string, FieldMeta> = {
  'payment.alipay.enabled': {
    type: 'switch',
  },
  'payment.alipay.mode': {
    type: 'select',
    options: [
      { label: '沙箱环境', value: 'sandbox' },
      { label: '生产环境', value: 'production' },
    ],
  },
  'payment.wechat.enabled': {
    type: 'switch',
  },
  'payment.fake.enabled': {
    type: 'switch',
  },
  'payment.wechat.mode': {
    type: 'select',
    options: [
      { label: '沙箱环境', value: 'sandbox' },
      { label: '生产环境', value: 'production' },
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

/** Payment sub-groups for visual separation in the UI */
export const PAYMENT_SUB_GROUPS: Record<string, { label: string; keys: string[] }> = {
  alipay: {
    label: '支付宝付款',
    keys: ['alipay.enabled', 'alipay.mode', 'alipay.app_id', 'alipay.private_key', 'alipay.public_key'],
  },
  wechat: {
    label: '微信付款',
    keys: ['wechat.enabled', 'wechat.mode', 'wechat.app_id', 'wechat.mch_id', 'wechat.api_key', 'wechat.cert'],
  },
  fake: {
    label: '模拟支付',
    keys: ['fake.enabled'],
  },
};

export function isFieldVisible(
  group: string,
  key: string,
  values: Record<string, string>,
): boolean {
  if (group === 'payment') {
    // Show alipay fields only when alipay is enabled
    if (key.startsWith('alipay.') && key !== 'alipay.enabled') {
      return values['payment.alipay.enabled'] === '1';
    }
    // Show wechat fields only when wechat is enabled
    if (key.startsWith('wechat.') && key !== 'wechat.enabled') {
      return values['payment.wechat.enabled'] === '1';
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

const LOCAL_KEYS = new Set(['local.public_base_url']);

const OSS_KEYS = new Set([
  'oss.bucket',
  'oss.endpoint',
  'oss.public_base_url',
  'oss.access_key',
  'oss.secret_key',
]);

const GROUP_FIELD_ORDER: Record<string, string[]> = {
  order: [
    'auto_cancel_minutes',
    'share_title',
    'share_message',
    'share_copy_text',
  ],
  payment: [
    // Alipay group
    'alipay.enabled',
    'alipay.mode',
    'alipay.app_id',
    'alipay.private_key',
    'alipay.public_key',
    // WeChat group
    'wechat.enabled',
    'wechat.mode',
    'wechat.app_id',
    'wechat.mch_id',
    'wechat.api_key',
    'wechat.cert',
    // Fake payment group
    'fake.enabled',
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
  if (group === 'payment' && key === 'alipay.mode') {
    return '沙箱: 开发测试环境 / 生产: 线上真实交易';
  }
  if (group === 'payment' && key === 'wechat.mode') {
    return '沙箱: 开发测试环境 / 生产: 线上真实交易';
  }
  if (group === 'payment' && key === 'fake.enabled') {
    return '开启后可在线上环境使用模拟支付调试，不产生真实交易';
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

/**
 * Get sub-groups for payment config items, used for visual separation.
 * Returns undefined for non-payment groups.
 */
export function getPaymentSubGroups(items: { key: string }[]): { label: string; items: { key: string }[] }[] | undefined {
  if (items.length === 0) {
    return undefined;
  }

  const hasPaymentItem = items.some(
    item => item.key.startsWith('alipay.') || item.key.startsWith('wechat.') || item.key.startsWith('fake.'),
  );
  if (!hasPaymentItem) {
    return undefined;
  }

  const alipayKeys = new Set(PAYMENT_SUB_GROUPS.alipay.keys);
  const wechatKeys = new Set(PAYMENT_SUB_GROUPS.wechat.keys);
  const fakeKeys = new Set(PAYMENT_SUB_GROUPS.fake.keys);

  const alipayItems = items.filter(item => alipayKeys.has(item.key));
  const wechatItems = items.filter(item => wechatKeys.has(item.key));
  const fakeItems = items.filter(item => fakeKeys.has(item.key));
  const otherItems = items.filter(
    item => !alipayKeys.has(item.key) && !wechatKeys.has(item.key) && !fakeKeys.has(item.key),
  );

  const groups: { label: string; items: { key: string }[] }[] = [];
  if (alipayItems.length > 0) {
    groups.push({ label: PAYMENT_SUB_GROUPS.alipay.label, items: alipayItems });
  }
  if (wechatItems.length > 0) {
    groups.push({ label: PAYMENT_SUB_GROUPS.wechat.label, items: wechatItems });
  }
  if (fakeItems.length > 0) {
    groups.push({ label: PAYMENT_SUB_GROUPS.fake.label, items: fakeItems });
  }
  if (otherItems.length > 0) {
    groups.push({ label: '其他', items: otherItems });
  }

  return groups.length > 0 ? groups : undefined;
}
