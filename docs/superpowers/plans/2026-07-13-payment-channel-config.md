# 支付渠道配置化 — 实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 后端支付渠道（支付宝/微信/模拟支付）全部通过 SystemConfig 配置开关控制，App 前端全动态获取。

**Architecture:** 后端新增 `fake.enabled` 配置（默认关闭），`PaymentChannelPolicy::fakeAllowed()` 改为配置优先、环境 fallback 的判定逻辑。App 端移除所有硬编码渠道列表和微信 AppID，改为通过 API 动态获取。

**Tech Stack:** Laravel 12 / PHP 8.4 / React Native 0.76 / TypeScript

## Global Constraints

- PHP 8.4 / Laravel 12 / MySQL 8.0 / Redis 7.4（锁定）
- DDD 分层，每个新功能必须配套测试
- `fake.enabled=0` 时行为必须与修改前完全一致（向后兼容）
- 微信 AppID 从后端 `wechat.app_id` 配置动态获取，App 不作为静态常量

---

### Task 1: Migration — 新增 fake.enabled 配置记录

**Files:**
- Create: `database/migrations/2026_07_13_145000_add_fake_enabled_payment_config.php`
- Modify: `database/seeders/SystemConfigSeeder.php`
- Test: `tests/Feature/Infrastructure/EloquentSystemConfigRepositoryTest.php`

**Interfaces:**
- Produces: `payment.fake.enabled` 配置记录（默认 `0`），写入 `system_configs` 表

- [x] **Step 1: 创建 Migration**

```php
// database/migrations/2026_07_13_145000_add_fake_enabled_payment_config.php
SystemConfigModel::updateOrCreate(
    ['group' => 'payment', 'key' => 'fake.enabled'],
    [
        'value' => '0',
        'is_sensitive' => false,
        'description' => '模拟支付 - 启用（开启后可在线上环境使用模拟支付调试）',
    ],
);
```

- [x] **Step 2: 更新 Seeder**

在 `SystemConfigSeeder.php` 的 `payment` 分组中新增 `fake.enabled` 记录。

- [x] **Step 3: 运行 Migration**

```bash
docker compose exec backend php artisan migrate
```

- [x] **Step 4: 更新测试计数**

`EloquentSystemConfigRepositoryTest` 中的 `assertCount(9, ...)` → `assertCount(10, ...)`。

- [x] **Step 5: 运行测试确认通过**

---

### Task 2: PaymentConfigReader — isEnabled/isConfigured 支持 fake 渠道

**Files:**
- Modify: `app/Infrastructure/Payment/PaymentConfigReader.php`
- Test: `tests/Unit/Infrastructure/Payment/PaymentConfigReaderTest.php`

**Interfaces:**
- Produces: `PaymentConfigReader::isEnabled('fake')` 读取 `fake.enabled`；`isConfigured('fake')` 返回 `true`（无必填密钥）

- [x] **Step 1: 修改 `isEnabled()`**

```php
match ($channel) {
    'alipay_sandbox' => 'alipay.enabled',
    'wechat' => 'wechat.enabled',
    'fake' => 'fake.enabled',
    default => null,
};
```

- [x] **Step 2: 修改 `isConfigured()`**

```php
match ($channel) {
    'alipay_sandbox' => self::ALIPAY,
    'wechat' => self::WECHAT,
    'fake' => null,        // fake 无必填密钥
    default => null,
};
// $group === null && $channel === 'fake' → always configured
```

- [x] **Step 3: 新增 Unit 测试**

测试用例：`fake_is_configured_by_default`、`fake_is_enabled_when_config_is_1`、`fake_is_not_enabled_when_config_is_0`、`fake_is_not_enabled_when_not_set`、`fake_is_available_when_enabled`、`fake_is_not_available_when_not_enabled`

- [x] **Step 4: 运行测试确认通过**

---

### Task 3: PaymentChannelPolicy — 配置优先的 fakeAllowed

**Files:**
- Modify: `app/Infrastructure/Payment/PaymentChannelPolicy.php`

**Interfaces:**
- Consumes: `PaymentConfigReader::isEnabled()`
- Produces: `fakeAllowed()` 改为实例方法；`wechatAppId()` 返回 `wechat.app_id`

- [x] **Step 1: 修改 `fakeAllowed()`**

```php
public function fakeAllowed(): bool
{
    if ($this->config->isEnabled(PaymentChannel::FAKE)) {
        return true;
    }
    return app()->environment('local', 'testing');
}
```

Deprecated 的静态方法改为 `fakeAllowedStatic()` 保持仅环境判断。

- [x] **Step 2: 新增 `wechatAppId()`**

```php
public function wechatAppId(): string
{
    return $this->config->get('wechat.app_id');
}
```

- [x] **Step 3: 更新 deprecated 静态方法**

`selfPayChannelsStatic()` / `proxyPayChannelsStatic()` 中 `self::fakeAllowed()` → `self::fakeAllowedStatic()`（保持静态上下文可用）。

---

### Task 4: Controller — API 响应增加 wechat_app_id

**Files:**
- Modify: `app/Http/Controllers/Catalog/PaymentChannelsController.php`
- Test: `tests/Feature/Catalog/PaymentChannelsApiTest.php`

- [x] **Step 1: 修改 response**

```php
$wechatAppId = $this->policy->wechatAppId();

return ApiResponse::success([
    'self_pay' => $selfPayChannels,
    'proxy_pay' => $proxyPayChannels,
    'wechat_app_id' => $wechatAppId !== '' ? $wechatAppId : null,
]);
```

- [x] **Step 2: 新增 Feature 测试**

测试用例：`returns_wechat_app_id_in_response`（空值时 null）、`returns_wechat_app_id_when_configured`（有值时返回）

- [x] **Step 3: 运行测试确认通过**

---

### Task 5: App types/config — 支持动态 AppID

**Files:**
- Modify: `app/src/types/order.ts`
- Modify: `app/src/config/payment.ts`

- [x] **Step 1: `types/order.ts` 增加字段**

```typescript
export interface PaymentChannelsResult {
  self_pay: PayChannelOption[];
  proxy_pay: PayChannelOption[];
  wechat_app_id?: string;  // ← 新增
}
```

- [x] **Step 2: `config/payment.ts` 改为运行时变量**

```typescript
let _wechatAppId: string | null = null;
export function getWechatAppId(): string | null { return _wechatAppId; }
export function setWechatAppId(appId: string): void { _wechatAppId = appId; }
```

---

### Task 6: App paymentLauncher — 动态获取 AppID

**Files:**
- Modify: `app/src/services/paymentLauncher.ts`

- [x] **Step 1: 替换 import**

`import {WECHAT_APP_ID}` → `import {getWechatAppId}`

- [x] **Step 2: 更新 `ensureWechatRegistered()`**

```typescript
function ensureWechatRegistered(): void {
  if (wechatRegistered) return;
  const appId = getWechatAppId();
  if (!appId) return;
  WeChat.registerApp(appId, '');
  wechatRegistered = true;
}
```

- [x] **Step 3: 更新 `launchWechatPay()`/`shareToWechat()`**

AppID 为空时抛错误或返回 `unavailable`。

---

### Task 7: App payChannels — 移除硬编码，缓存 AppID

**Files:**
- Modify: `app/src/utils/payChannels.ts`

- [x] **Step 1: 重写 `selfPayChannels()`**

```typescript
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
```
去掉 `try/catch` fallback，API 失败时直接 throw。

---

### Task 8: App CheckoutScreen — 修复 useMemo bug

**Files:**
- Modify: `app/src/screens/CheckoutScreen.tsx`

- [x] **Step 1: 修复异步加载**

`useMemo(() => selfPayChannels(), [])`（Bug：Promise 不会工作）→
```typescript
useEffect(() => {
  void (async () => {
    try {
      const channels = await selfPayChannels();
      setChannelOptions(channels);
      if (channels.length > 0) setChannel(channels[0].value);
    } catch { /* 保持空列表 */ }
  })();
}, []);
```

- [x] **Step 2: 无渠道时显示提示**

`channelOptions.length === 0` 时渲染「暂无可用的支付方式」。

---

### Task 9: 全量验证

- [x] **Step 1: 运行全量测试**

```bash
./scripts/docker-test.sh
```

Expected: 全部通过（288 passed, 769 assertions）

- [x] **Step 2: 提交所有改动**
