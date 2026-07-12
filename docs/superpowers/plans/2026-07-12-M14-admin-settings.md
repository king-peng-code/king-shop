# M14 管理后台系统配置 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现管理后台系统配置页（4 Tab 分组表单 + 敏感项权限），对接 M01 Config API，并补充后端 super_admin 敏感项校验。

**Architecture:** 混合渲染 — API `groups[].items` 驱动字段列表，`configFieldMeta.ts` 覆盖 Select/InputNumber/TextArea；`UpdateSystemConfigsHandler` 接收 `actorRole` 拦截非 super_admin 的敏感项写入；假定 M11 AdminLayout / AuthContext / api/client 已存在。

**Tech Stack:** React **18.3.1** · TypeScript **~5.6** · Vite **5.x** · Ant Design **5.x** · Laravel **12** · PHP **8.4**

## Global Constraints

- React **18.3.1**（禁止 17 / 18.0~18.2 / 19）
- TypeScript **~5.6** / Vite **5.x**（禁止 Vite 4）
- Node **20 LTS**（≥18，见根目录 `.nvmrc`）
- PHP **8.4** / Laravel **12**（Backend 测试在 Docker 内执行）
- API 前缀 `/api/v1/`，响应 `{ "code": 0, "message": "ok", "data": {} }`
- Frontend **不直连数据库**，只调 Backend API
- 页面放 `frontend/src/pages/`，API 放 `frontend/src/api/`，组件放 `frontend/src/components/`
- **假定 M11 已合并**（Login、AdminLayout、AuthContext、`api/client.ts` 可用）
- 后端 DDD 变更必须有自动化测试；完成门槛 `./scripts/docker-test.sh` 全通过
- 前端完成门槛：`npm run build` 无 TS 错误 + 手工验收清单全通过

---

### Task 1: 后端敏感配置权限（TDD）

**Files:**
- Create: `backend/app/Domain/SystemConfig/Exceptions/SensitiveConfigForbiddenException.php`
- Modify: `backend/app/Application/SystemConfig/UpdateSystemConfigs/UpdateSystemConfigsHandler.php`
- Modify: `backend/app/Http/Controllers/Admin/SystemConfigController.php`
- Modify: `backend/tests/Unit/Application/SystemConfig/UpdateSystemConfigsHandlerTest.php`
- Modify: `backend/tests/Feature/Admin/SystemConfigApiTest.php`

**Interfaces:**
- Produces: `SensitiveConfigForbiddenException` — HTTP 403, message `无权修改敏感配置`
- Produces: `UpdateSystemConfigsHandler::handle(array $items, string $actorRole): array`
- Consumes: `SystemConfigRepositoryInterface::findByGroupAndKey()` 返回 `?SystemConfig`（含 `isSensitive`）

- [ ] **Step 1: Write failing unit test for admin updating sensitive config**

```php
// backend/tests/Unit/Application/SystemConfig/UpdateSystemConfigsHandlerTest.php
// 在现有文件中新增 test 方法

#[Test]
public function admin_cannot_update_sensitive_config_value(): void
{
    $repository = $this->createMock(SystemConfigRepositoryInterface::class);
    $repository->method('findByGroupAndKey')
        ->with('payment', 'wechat.mch_id')
        ->willReturn(new SystemConfig('payment', 'wechat.mch_id', 'old', true, '微信商户号'));

    $getHandler = $this->createMock(GetSystemConfigsHandler::class);
    $handler = new UpdateSystemConfigsHandler($repository, $getHandler);

    $this->expectException(SensitiveConfigForbiddenException::class);

    $handler->handle(
        [new SystemConfigItemDto('payment', 'wechat.mch_id', 'new-value')],
        'admin',
    );
}
```

同时在文件顶部添加：

```php
use App\Domain\SystemConfig\Exceptions\SensitiveConfigForbiddenException;
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./scripts/docker-test.sh --filter=admin_cannot_update_sensitive_config_value
```

Expected: FAIL — `handle()` 不接受第二参数或异常未抛出

- [ ] **Step 3: Create exception and update handler**

```php
// backend/app/Domain/SystemConfig/Exceptions/SensitiveConfigForbiddenException.php
<?php

namespace App\Domain\SystemConfig\Exceptions;

use App\Exceptions\BusinessException;

class SensitiveConfigForbiddenException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(403, '无权修改敏感配置', 403);
    }
}
```

```php
// backend/app/Application/SystemConfig/UpdateSystemConfigs/UpdateSystemConfigsHandler.php
// 完整替换 handle 方法签名与实现

/** @param SystemConfigItemDto[] $items */
public function handle(array $items, string $actorRole): array
{
    foreach ($items as $item) {
        if ($item->value === SystemConfig::MASK_PLACEHOLDER) {
            continue;
        }

        $existing = $this->repository->findByGroupAndKey($item->group, $item->key);

        if (
            $existing !== null
            && $existing->isSensitive
            && $actorRole !== 'super_admin'
        ) {
            throw new SensitiveConfigForbiddenException;
        }

        $this->repository->updateValue($item->group, $item->key, $item->value);
    }

    return $this->getHandler->handle();
}
```

文件顶部添加：

```php
use App\Domain\SystemConfig\Exceptions\SensitiveConfigForbiddenException;
```

- [ ] **Step 4: Update controller to pass actor role**

```php
// backend/app/Http/Controllers/Admin/SystemConfigController.php
// update() 方法最后一行改为：

return ApiResponse::success($handler->handle($items, $request->user()->role));
```

- [ ] **Step 5: Fix existing unit test call signature**

将 `UpdateSystemConfigsHandlerTest` 中现有 `handle()` 调用改为传入 `'super_admin'`：

```php
$result = $handler->handle(
    [
        new SystemConfigItemDto('app', 'name', '内部晚餐'),
        new SystemConfigItemDto('payment', 'wechat.mch_id', SystemConfig::MASK_PLACEHOLDER),
    ],
    'super_admin',
);
```

新增 super_admin 可通过的 unit test：

```php
#[Test]
public function super_admin_can_update_sensitive_config_value(): void
{
    $repository = $this->createMock(SystemConfigRepositoryInterface::class);
    $repository->expects($this->once())
        ->method('findByGroupAndKey')
        ->with('payment', 'wechat.mch_id')
        ->willReturn(new SystemConfig('payment', 'wechat.mch_id', 'old', true, '微信商户号'));
    $repository->expects($this->once())
        ->method('updateValue')
        ->with('payment', 'wechat.mch_id', 'new-value');

    $getHandler = $this->createMock(GetSystemConfigsHandler::class);
    $getHandler->method('handle')->willReturn(['groups' => []]);

    $handler = new UpdateSystemConfigsHandler($repository, $getHandler);

    $result = $handler->handle(
        [new SystemConfigItemDto('payment', 'wechat.mch_id', 'new-value')],
        'super_admin',
    );

    $this->assertSame(['groups' => []], $result);
}
```

- [ ] **Step 6: Write failing feature tests**

```php
// backend/tests/Feature/Admin/SystemConfigApiTest.php
// 在类末尾新增：

#[Test]
public function admin_cannot_update_sensitive_config_via_api(): void
{
    $admin = UserModel::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/admin/configs', [
            'configs' => [
                ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1234567890'],
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 403)
        ->assertJsonPath('message', '无权修改敏感配置');
}

#[Test]
public function super_admin_can_update_sensitive_config_via_api(): void
{
    $superAdmin = UserModel::factory()->superAdmin()->create();
    $token = $superAdmin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/admin/configs', [
            'configs' => [
                ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '9876543210'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('code', 0);
}

#[Test]
public function admin_can_update_non_sensitive_config_via_api(): void
{
    $admin = UserModel::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/admin/configs', [
            'configs' => [
                ['group' => 'app', 'key' => 'name', 'value' => '管理员可改'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('code', 0);

    $appGroup = collect($this->withToken($token)->getJson('/api/v1/admin/configs')->json('data.groups'))
        ->firstWhere('name', 'app');
    $nameItem = collect($appGroup['items'])->firstWhere('key', 'name');
    $this->assertSame('管理员可改', $nameItem['value']);
}
```

- [ ] **Step 7: Run all config tests**

```bash
./scripts/docker-test.sh --filter=SystemConfig
```

Expected: PASS（全部 SystemConfig 相关测试绿色）

- [ ] **Step 8: Commit**

```bash
git add backend/app/Domain/SystemConfig/Exceptions/SensitiveConfigForbiddenException.php \
  backend/app/Application/SystemConfig/UpdateSystemConfigs/UpdateSystemConfigsHandler.php \
  backend/app/Http/Controllers/Admin/SystemConfigController.php \
  backend/tests/Unit/Application/SystemConfig/UpdateSystemConfigsHandlerTest.php \
  backend/tests/Feature/Admin/SystemConfigApiTest.php
git commit -m "feat(M14): restrict sensitive config updates to super_admin"
```

---

### Task 2: 前端类型与 Config API

**Files:**
- Create: `frontend/src/types/config.ts`
- Create: `frontend/src/api/configs.ts`

**Interfaces:**
- Consumes: `request<T>()` from `frontend/src/api/client.ts`（M11 已有）
- Produces: `ConfigItem`, `ConfigGroup`, `ConfigListResult`, `ConfigUpdatePayload`
- Produces: `configsApi.get()`, `configsApi.update(configs)`

- [ ] **Step 1: Create types**

```typescript
// frontend/src/types/config.ts
export interface ConfigItem {
  key: string;
  value: string;
  is_sensitive: boolean;
  description: string | null;
}

export interface ConfigGroup {
  name: string;
  label: string;
  items: ConfigItem[];
}

export interface ConfigListResult {
  groups: ConfigGroup[];
}

export interface ConfigUpdatePayload {
  group: string;
  key: string;
  value: string;
}
```

- [ ] **Step 2: Create API module**

```typescript
// frontend/src/api/configs.ts
import { request } from './client';
import type { ConfigListResult, ConfigUpdatePayload } from '../types/config';

export const configsApi = {
  get(): Promise<ConfigListResult> {
    return request<ConfigListResult>('/admin/configs');
  },

  update(configs: ConfigUpdatePayload[]): Promise<ConfigListResult> {
    return request<ConfigListResult>('/admin/configs', {
      method: 'PUT',
      body: JSON.stringify({ configs }),
    });
  },
};
```

- [ ] **Step 3: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS（无 TS 错误）

- [ ] **Step 4: Commit**

```bash
git add frontend/src/types/config.ts frontend/src/api/configs.ts
git commit -m "feat(M14): add config types and API client"
```

---

### Task 3: 字段元数据与联动规则

**Files:**
- Create: `frontend/src/config/configFieldMeta.ts`

**Interfaces:**
- Produces: `getFieldMeta(group, key, isSensitive): FieldMeta`
- Produces: `isFieldVisible(group, key, values): boolean`
- Produces: `FieldMeta` type — `{ type: 'input' | 'password' | 'textarea' | 'select' | 'number'; options?: {label,value}[]; min?: number; max?: number; rows?: number }`

- [ ] **Step 1: Create field meta module**

```typescript
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
```

- [ ] **Step 2: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add frontend/src/config/configFieldMeta.ts
git commit -m "feat(M14): add config field metadata and visibility rules"
```

---

### Task 4: ConfigGroupForm 组件

**Files:**
- Create: `frontend/src/components/ConfigGroupForm.tsx`

**Interfaces:**
- Consumes: `configsApi.update()`, `getFieldMeta()`, `isFieldVisible()`, `ConfigGroup`, `Role` from `../types/employee`
- Produces: `ConfigGroupForm` component — props `{ group, userRole, onSaved }`

- [ ] **Step 1: Implement ConfigGroupForm**

```typescript
// frontend/src/components/ConfigGroupForm.tsx
import { useMemo } from 'react';
import { Button, Form, Input, InputNumber, Select, message } from 'antd';
import { configsApi } from '../api/configs';
import { ApiError } from '../api/client';
import { getFieldMeta, isFieldVisible } from '../config/configFieldMeta';
import type { ConfigGroup } from '../types/config';
import type { Role } from '../types/employee';

interface ConfigGroupFormProps {
  group: ConfigGroup;
  userRole: Role;
  onSaved: (groups: ConfigGroup[]) => void;
}

function buildInitialValues(group: ConfigGroup): Record<string, string> {
  const values: Record<string, string> = {};
  for (const item of group.items) {
    values[item.key] = item.value;
  }
  return values;
}

export function ConfigGroupForm({
  group,
  userRole,
  onSaved,
}: ConfigGroupFormProps) {
  const [form] = Form.useForm<Record<string, string>>();
  const initialValues = useMemo(() => buildInitialValues(group), [group]);

  const watchedValues = Form.useWatch([], form) ?? initialValues;

  const visibilityContext = useMemo(() => {
    const ctx: Record<string, string> = { ...initialValues, ...watchedValues };
    for (const [k, v] of Object.entries(ctx)) {
      ctx[`${group.name}.${k}`] = v;
    }
    return ctx;
  }, [group.name, initialValues, watchedValues]);

  const visibleItems = group.items.filter((item) =>
    isFieldVisible(group.name, item.key, visibilityContext),
  );

  const handleSubmit = async (values: Record<string, string>) => {
    const configs = group.items.map((item) => ({
      group: group.name,
      key: item.key,
      value: values[item.key] ?? item.value,
    }));

    try {
      const result = await configsApi.update(configs);
      message.success('保存成功');
      const updated = result.groups.find((g) => g.name === group.name);
      if (updated) {
        form.setFieldsValue(buildInitialValues(updated));
      }
      onSaved(result.groups);
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.code === 403) {
          message.error('无权修改敏感配置');
          return;
        }
        if (error.status === 422 && error.errors) {
          const first = Object.values(error.errors)[0]?.[0];
          message.error(first ?? '校验失败');
          return;
        }
        message.error(error.message);
        return;
      }
      message.error('网络异常，请重试');
    }
  };

  const renderField = (item: (typeof group.items)[number]) => {
    const meta = getFieldMeta(group.name, item.key, item.is_sensitive);
    const readOnlySensitive =
      item.is_sensitive && userRole !== 'super_admin';

    const label = item.description ?? item.key;

    if (readOnlySensitive) {
      return <Input disabled value="****" />;
    }

    switch (meta.type) {
      case 'select':
        return (
          <Select
            options={meta.options}
            placeholder={`请选择${label}`}
          />
        );
      case 'number':
        return (
          <InputNumber
            min={meta.min}
            max={meta.max}
            style={{ width: '100%' }}
          />
        );
      case 'textarea':
        return <Input.TextArea rows={meta.rows ?? 4} />;
      case 'password':
        return <Input.Password placeholder={`请输入${label}`} />;
      default:
        return <Input placeholder={`请输入${label}`} />;
    }
  };

  return (
    <Form
      form={form}
      layout="vertical"
      initialValues={initialValues}
      onFinish={handleSubmit}
    >
      {visibleItems.map((item) => (
        <Form.Item
          key={item.key}
          name={item.key}
          label={item.description ?? item.key}
          rules={[{ required: !item.is_sensitive, message: '此项不能为空' }]}
        >
          {renderField(item)}
        </Form.Item>
      ))}

      <Form.Item>
        <Button type="primary" htmlType="submit">
          保存
        </Button>
      </Form.Item>
    </Form>
  );
}
```

- [ ] **Step 2: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add frontend/src/components/ConfigGroupForm.tsx
git commit -m "feat(M14): add ConfigGroupForm with field meta and visibility"
```

---

### Task 5: SettingsPage 与路由/菜单

**Files:**
- Create: `frontend/src/pages/settings/SettingsPage.tsx`
- Modify: `frontend/src/components/AdminLayout.tsx`
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `configsApi.get()`, `ConfigGroupForm`, `useAuth()` from M11 AuthContext
- Produces: `/settings` 路由可访问的配置页

- [ ] **Step 1: Create SettingsPage**

```typescript
// frontend/src/pages/settings/SettingsPage.tsx
import { useCallback, useEffect, useState } from 'react';
import { Alert, Spin, Tabs, Typography } from 'antd';
import { configsApi } from '../../api/configs';
import { ConfigGroupForm } from '../../components/ConfigGroupForm';
import { useAuth } from '../../contexts/AuthContext';
import type { ConfigGroup } from '../../types/config';

export function SettingsPage() {
  const { user } = useAuth();
  const [groups, setGroups] = useState<ConfigGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadConfigs = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await configsApi.get();
      setGroups(result.groups);
    } catch {
      setError('加载配置失败，请重试');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadConfigs();
  }, [loadConfigs]);

  if (loading) {
    return <Spin size="large" style={{ display: 'block', margin: '80px auto' }} />;
  }

  if (error) {
    return <Alert type="error" message={error} showIcon />;
  }

  if (!user) {
    return null;
  }

  const tabItems = groups.map((group) => ({
    key: group.name,
    label: group.label,
    children: (
      <ConfigGroupForm
        group={group}
        userRole={user.role}
        onSaved={(updatedGroups) => setGroups(updatedGroups)}
      />
    ),
  }));

  return (
    <div>
      <Typography.Title level={4} style={{ marginBottom: 16 }}>
        系统配置
      </Typography.Title>
      <Tabs items={tabItems} />
    </div>
  );
}
```

- [ ] **Step 2: Add menu item to AdminLayout**

在 `frontend/src/components/AdminLayout.tsx` 中：

1. 导入 `SettingOutlined` from `@ant-design/icons`
2. 更新 `selectedKey` 逻辑：

```typescript
const selectedKey = location.pathname.startsWith('/settings')
  ? 'settings'
  : location.pathname.startsWith('/employees')
    ? 'employees'
    : '';
```

3. 在 `Menu` `items` 数组追加：

```typescript
{
  key: 'settings',
  icon: <SettingOutlined />,
  label: '系统配置',
  onClick: () => navigate('/settings'),
},
```

- [ ] **Step 3: Register route in App.tsx**

1. 导入 `SettingsPage` from `./pages/settings/SettingsPage`
2. 在 AdminLayout 子路由中追加：

```typescript
<Route path="settings" element={<SettingsPage />} />
```

- [ ] **Step 4: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/src/pages/settings/SettingsPage.tsx \
  frontend/src/components/AdminLayout.tsx \
  frontend/src/App.tsx
git commit -m "feat(M14): add settings page with route and sidebar menu"
```

---

### Task 6: 联调与验收

**Files:**
- Modify: `docs/superpowers/specs/2026-07-12-internal-mall-design.md`（更新 M14 状态）

- [ ] **Step 1: Start backend**

```bash
./scripts/dev-up.sh
```

- [ ] **Step 2: Run full backend tests**

```bash
./scripts/docker-test.sh
```

Expected: 全部 PASS

- [ ] **Step 3: Start frontend and manual smoke test**

```bash
cd frontend && npm run dev
```

手工验收清单：

1. super_admin（`13800000000` / `admin123`）登录 → 侧边栏「系统配置」→ 4 Tab 加载
2. 修改「基础信息」商城名称 → 保存成功 → 刷新仍为新值
3. `payment.provider` 切 `wechat` → 仅显示微信字段；切 `alipay_sandbox` → 仅显示支付宝字段
4. `storage.driver` 切 `local` → OSS 字段隐藏；切 `oss` → OSS 字段显示
5. admin 账号登录 → 敏感项显示 `****` 且 disabled → 保存非敏感项成功
6. admin 用 curl 直接 PUT 敏感项 → 403

- [ ] **Step 4: Update module status in internal mall spec**

将 `docs/superpowers/specs/2026-07-12-internal-mall-design.md` 表格 M14 行改为：

```
| M14 | 后台系统配置 | ✅ 已完成 | 2026-07-12 |
```

- [ ] **Step 5: Final commit**

```bash
git add docs/superpowers/specs/2026-07-12-internal-mall-design.md \
  docs/superpowers/specs/2026-07-12-M14-admin-settings-design.md \
  docs/superpowers/plans/2026-07-12-M14-admin-settings.md
git commit -m "docs(M14): mark admin settings module complete"
```

---

## Plan Self-Review

| Spec 要求 | 对应 Task |
|---|---|
| 4 Tab 分组表单 | Task 4, 5 |
| 敏感项 admin 只读 | Task 4 (`readOnlySensitive`) |
| 后端 super_admin 校验 | Task 1 |
| provider/driver 联动 | Task 3 |
| 侧边栏菜单 + 路由 | Task 5 |
| API 对接 GET/PUT | Task 2 |
| 后端测试 | Task 1, 6 |
| `npm run build` | Task 2–5, 6 |
| 假定 M11 已完成 | Global Constraints |

无 TBD / 占位符；类型名 `ConfigGroup`、`configsApi`、`SensitiveConfigForbiddenException` 全文一致。

---

**Plan 路径：** `docs/superpowers/plans/2026-07-12-M14-admin-settings.md`
