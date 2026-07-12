# M14 — 管理后台：系统配置 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **依赖：** M01 系统配置管理（已完成）、M11 管理后台基础（假定已完成）  
> **后续依赖方：** M15 数据统计、M16 部署

---

## 1. 目标

实现 React 管理后台 **系统配置** 页面：4 个 Tab 分组表单（基础信息 / 支付 / 存储 / 订单），对接 M01 `GET/PUT /api/v1/admin/configs`；敏感配置项前后端均限制仅 `super_admin` 可修改。

**交付物：**
- `frontend/src/pages/settings/SettingsPage.tsx`
- `frontend/src/components/ConfigGroupForm.tsx`
- `frontend/src/api/configs.ts`
- `frontend/src/types/config.ts`
- `frontend/src/config/configFieldMeta.ts` — 特殊控件与联动规则
- `AdminLayout` 侧边栏新增「系统配置」→ `/settings`
- 后端敏感项权限校验 + Feature Test

**非目标（M14 不做）：**
- M11 登录/Layout/路由守卫（假定 M11 已合并）
- 配置变更历史 / 审计日志
- 前端自动化测试（Vitest / Playwright）
- Redis 配置缓存

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| 前置依赖 | **方案 A**：仅加配置页 + 菜单 | 与模块依赖顺序一致，不重复 M11 |
| 敏感项权限 | **方案 B**：前后端均限制 | 安全边界在后端，前端配合只读展示 |
| 页面布局 | **方案 A**：Tabs + 分组独立保存 | 结构清晰，与 Ant Design 后台风格一致 |
| 表单渲染 | **方案 3**：API 驱动 + `fieldMeta` 覆盖 | 兼顾动态扩展与下拉/数字/联动体验 |
| 测试 | 后端 Feature Test + 前端手工验收 | M14 是 frontend 增量模块，后端补权限测试 |

---

## 3. 权限模型

| 角色 | 查看配置页 | 编辑非敏感项 | 编辑敏感项 |
|---|---|---|---|
| `admin` | ✅ | ✅ | ❌ 只读显示 `****` |
| `super_admin` | ✅ | ✅ | ✅ |

### 后端逻辑（`UpdateSystemConfigsHandler`）

对每个 PUT 项：

1. `value === "****"` → 跳过（保留原值，与 M01 一致）
2. 查 `repository.findByGroupAndKey()` 得 `is_sensitive`
3. 若 `is_sensitive === true` 且 `value !== "****"` 且 `actorRole !== super_admin` → 抛 `SensitiveConfigForbiddenException`（HTTP 403）

Controller 传入 `$request->user()->role` 作为 `actorRole`。

### 前端配合

- `admin`：`is_sensitive=true` 字段 `disabled`，显示 `****`；提交时仍传 `****` 占位
- `super_admin`：敏感字段用 `Input.Password` 或 `TextArea`（证书），可编辑

---

## 4. 页面结构

```
SettingsPage
├── PageHeader「系统配置」
├── Tabs（defaultActiveKey 按 API 返回顺序）
│   ├── Tab「基础信息」(app)     → ConfigGroupForm
│   ├── Tab「支付配置」(payment)  → ConfigGroupForm + provider 联动
│   ├── Tab「存储配置」(storage)  → ConfigGroupForm + driver 联动
│   └── Tab「订单配置」(order)    → ConfigGroupForm
└── 每个 Tab 内 Form + 底部「保存」按钮（仅提交当前组）
```

### 目录结构

```
frontend/src/
├── api/
│   └── configs.ts
├── components/
│   └── ConfigGroupForm.tsx
├── config/
│   └── configFieldMeta.ts
├── pages/
│   └── settings/
│       └── SettingsPage.tsx
└── types/
    └── config.ts
```

---

## 5. 字段元数据与联动

### `configFieldMeta.ts`

| group.key | 控件 | 选项 / 校验 |
|---|---|---|
| `payment.provider` | Select | `alipay_sandbox`（支付宝沙箱）、`wechat`（微信支付） |
| `storage.driver` | Select | `local`（本地存储）、`oss`（阿里云 OSS） |
| `order.auto_cancel_minutes` | InputNumber | min: 1, max: 1440 |
| `payment.wechat.cert` | TextArea | rows: 4，证书 PEM 粘贴 |
| `payment.alipay.private_key` | TextArea | rows: 4 |
| 其他 `is_sensitive=true` | Input.Password | super_admin 可编辑 |
| 其他非敏感 | Input | 默认文本 |

### 联动可见性（`isFieldVisible(group, key, formValues)`）

| 条件 | 隐藏字段 |
|---|---|
| `payment.provider = alipay_sandbox` | `payment.wechat.*` |
| `payment.provider = wechat` | `payment.alipay.*` |
| `storage.driver = local` | `storage.oss.*` |
| `storage.driver = oss` | 无（显示全部 OSS 字段） |

---

## 6. API 对接

### GET `/api/v1/admin/configs`

响应（M01 已有）：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "groups": [
      {
        "name": "app",
        "label": "基础信息",
        "items": [
          {
            "key": "name",
            "value": "内部下午茶",
            "is_sensitive": false,
            "description": "商城名称"
          }
        ]
      }
    ]
  }
}
```

### PUT `/api/v1/admin/configs`

请求体（每 Tab 保存时提交该组全部 items）：

```json
{
  "configs": [
    { "group": "app", "key": "name", "value": "内部晚餐" }
  ]
}
```

响应：更新后的完整配置（同 GET `data` 结构）。

### TypeScript 类型

```typescript
interface ConfigItem {
  key: string;
  value: string;
  is_sensitive: boolean;
  description: string | null;
}

interface ConfigGroup {
  name: string;
  label: string;
  items: ConfigItem[];
}

interface ConfigListResult {
  groups: ConfigGroup[];
}

interface ConfigUpdatePayload {
  group: string;
  key: string;
  value: string;
}
```

### 前端 API 方法

```typescript
configsApi.get(): Promise<ConfigListResult>
configsApi.update(configs: ConfigUpdatePayload[]): Promise<ConfigListResult>
```

---

## 7. 组件设计

### ConfigGroupForm

**Props：**

```typescript
interface ConfigGroupFormProps {
  group: ConfigGroup;
  userRole: Role;
  onSaved: (groups: ConfigGroup[]) => void;
}
```

**行为：**
1. 用 `items` 初始化 Ant Design `Form`
2. 按 `configFieldMeta` 渲染控件；`admin` + `is_sensitive` → `disabled`
3. `provider` / `driver` 变更时重新计算可见字段
4. 提交：收集可见字段 → `{ group, key, value }[]` → `configsApi.update`
5. 成功：`message.success('保存成功')`，`onSaved` 刷新父级 state
6. 403：`message.error('无权修改敏感配置')`
7. 422：字段级错误

### SettingsPage

1. `useEffect` 加载 `configsApi.get()`
2. `useAuth()` 取 `user.role`
3. `Tabs` 渲染各 `ConfigGroupForm`
4. Loading / Error 状态处理

### AdminLayout 菜单扩展

在 M11 `AdminLayout` Sider 增加：

```typescript
{ key: '/settings', icon: <SettingOutlined />, label: '系统配置' }
```

### 路由（`App.tsx`）

```typescript
{ path: '/settings', element: <AdminRoute><SettingsPage /></AdminRoute> }
```

---

## 8. 后端补充

### 新增异常

`App\Domain\SystemConfig\Exceptions\SensitiveConfigForbiddenException`

```php
parent::__construct(403, '无权修改敏感配置', 403);
```

### 修改文件

| 文件 | 变更 |
|---|---|
| `UpdateSystemConfigsHandler.php` | 新增 `$actorRole` 参数，更新前校验敏感项 |
| `SystemConfigController.php` | `update()` 传入 `$request->user()->role` |
| `UpdateSystemConfigsHandlerTest.php` | 补 admin 改敏感项失败用例 |
| `SystemConfigApiTest.php` | admin 403 / super_admin 200 |

---

## 9. 错误处理

| 场景 | HTTP / code | UI 行为 |
|---|---|---|
| 未登录 | 401 | AuthContext 跳登录 |
| 非 admin | 403 | 「无权访问」 |
| admin 改敏感项 | 403 | `message.error('无权修改敏感配置')` |
| 校验失败 | 422 | 字段错误 / `message.error` |
| 网络异常 | — | `message.error('网络异常，请重试')` |

---

## 10. 开发环境

```bash
# 后端（Docker）
./scripts/dev-up.sh

# 前端（假定 M11 已合并）
cd frontend
cp .env.example .env   # VITE_API_BASE_URL=http://localhost:8000/api/v1
npm install
npm run dev
```

**测试账号：**
- super_admin：`13800000000` / `admin123`
- admin：由 super_admin 在员工管理创建

---

## 11. 验收标准

- [ ] 侧边栏可进入「系统配置」页，4 个 Tab 正确加载
- [ ] 敏感项对 `admin` 只读（`****`），`super_admin` 可编辑
- [ ] `admin` 绕过前端直接 PUT 敏感项，后端返回 403
- [ ] `super_admin` 可保存敏感项，DB 仍为密文
- [ ] `payment.provider` 切换后联动显示/隐藏对应字段
- [ ] `storage.driver` 切换后联动显示/隐藏 OSS 字段
- [ ] 各 Tab 独立保存成功，非当前 Tab 数据不变
- [ ] `npm run build` 无 TypeScript 错误
- [ ] `./scripts/docker-test.sh` 全部通过

---

## 12. 预估

**1 天**（与总体 Spec 一致）

| 任务 | 预估 |
|------|------|
| 后端敏感项权限 + 测试 | 1.5h |
| 类型 + API + fieldMeta | 1h |
| ConfigGroupForm + SettingsPage | 2.5h |
| 路由/菜单 + 联调 | 1h |
| 手工验收 | 0.5h |

---

**Spec 路径：** `docs/superpowers/specs/2026-07-12-M14-admin-settings-design.md`
