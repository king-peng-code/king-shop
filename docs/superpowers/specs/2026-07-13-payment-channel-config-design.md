# 支付渠道配置化 — 设计文档

## 概述

后端已通过 `SystemConfig` 实现了支付宝和微信支付的启用/模式配置开关。本任务将：

1. 后端新增 `fake.enabled` 配置，使模拟支付（调试模式）也可通过配置控制，支持线上调试
2. App 前端适配全动态渠道获取，移除硬编码降级，微信 AppID 改为从 API 动态获取

## 范围

- 后端：`PaymentConfigReader`、`PaymentChannelPolicy`、Migration、Controller
- App：`payChannels.ts`、`config/payment.ts`、`paymentLauncher.ts`、`CheckoutScreen.tsx`、`types/order.ts`
- 不涉及：frontend 管理后台、iOS/Android 原生层

## 后端设计

### Migration：新增 `fake.enabled` 配置

在 `payment` 分组新增一条记录：

| group | key | value | is_sensitive | description |
|-------|-----|-------|-------------|-------------|
| payment | `fake.enabled` | `0` | `false` | 模拟支付 - 启用 |

`SystemConfigSeeder` 同步添加。

### PaymentConfigReader

**`isEnabled()`** — `match` 增加 `'fake' => 'fake.enabled'`：

```php
match ($channel) {
    'alipay_sandbox' => 'alipay.enabled',
    'wechat' => 'wechat.enabled',
    'fake' => 'fake.enabled',
    default => null,
};
```

**`isConfigured()`** — `match` 增加 `'fake' => null`，`$group === null` 时返回 `true`（fake 无必填密钥 = 总是已配置）。

### PaymentChannelPolicy

`fakeAllowed()` 改为先检查配置，再 fallback 环境：

```php
public function fakeAllowed(): bool
{
    if ($this->config->isEnabled(PaymentChannel::FAKE)) {
        return true;
    }
    return app()->environment('local', 'testing');
}
```

### API 响应扩展

`GET /api/v1/payment-channels` 响应增加 `wechat_app_id`：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "self_pay": [...],
    "proxy_pay": [...],
    "wechat_app_id": "wx..."
  }
}
```

### 测试

- `PaymentChannelsApiTest`：新增 `fake_channel_can_be_enabled_by_config` 验证配置可开启 fake
- `PaymentConfigReaderTest`：新增 `isEnabled_fake`、`isConfigured_fake` 测试

## App 前端设计

### types/order.ts

`PaymentChannelsResult` 增加可选字段：

```typescript
export interface PaymentChannelsResult {
  self_pay: PayChannelOption[];
  proxy_pay: PayChannelOption[];
  wechat_app_id?: string;
}
```

### config/payment.ts

静态常量改为运行时变量：

```typescript
let _wechatAppId: string | null = null;

export function getWechatAppId(): string | null { return _wechatAppId; }
export function setWechatAppId(appId: string): void { _wechatAppId = appId; }
```

### payChannels.ts

- 去掉 `try/catch` + 硬编码 fallback
- API 成功时缓存 `wechat_app_id`
- API 失败时直接 throw，由调用方处理

### paymentLauncher.ts

- `ensureWechatRegistered()` 改为通过 `getWechatAppId()` 动态获取 AppID
- `launchWechatPay()` 增加 AppID 为空时的检查

### CheckoutScreen.tsx

- `useMemo(() => selfPayChannels(), [])` 改为 `useEffect` + 异步加载
- 默认支付渠道由 API 返回的第一个决定，去掉 `__DEV__ ? 'fake' : 'alipay_sandbox'` 硬编码
- 无可用渠道时 UI 展示「暂无可用的支付方式」

## 未修改部分

- `OrderDetailScreen.tsx`：已正确使用 `useEffect` + `selfPayChannels()`，无需改动
- `PaymentScreen.tsx`：支付流程逻辑不变，依赖后端 `pay_params` 中的 channel 决定行为
- `PaymentChannelPicker.tsx`：纯展示组件，无需改动
- `PaymentMethodPicker.tsx`：纯展示组件，无需改动

## 错误处理

- API 获取渠道失败 → 调用方 `handleApiError` 展示错误（已有机制）
- 无可用支付渠道 → 按钮置灰，显示「暂无可用的支付方式」
- 微信 AppID 未获取到 → `launchWechatPay` 抛明确错误信息
- `fake.enabled` 未开启 → 渠道列表不包含 fake，无副作用

## 向后兼容

- `fake.enabled=0`（默认值）→ 行为与当前完全相同（仅 local/testing 可用）
- 已有 API 消费者：`wechat_app_id` 是新增字段，旧客户端忽略即可
