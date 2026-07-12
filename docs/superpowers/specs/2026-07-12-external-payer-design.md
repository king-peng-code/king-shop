# 代付三方用户（External Payer）Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **状态：** 📋 待实现  
> **依赖：** M07 找人代付（已完成）、M06 支付网关（已完成）  
> **后续依赖方：** M16 生产支付配置（微信 OAuth + JSAPI）

---

## 1. 背景与问题

M07 代付设计假设 **付款人必须是系统员工**（`users` 表登录），支付成功后写入 `orders.paid_by_user_id`。

实际业务中，代付人往往是 **微信/支付宝上的外部联系人**，不是内部员工，不应占用 `users` 记录，也不应要求员工账号登录。

---

## 2. 目标

引入 **三方付款人（External Payer）** 独立实体，与员工 `users` 解耦：

- 代付 H5 **无需员工登录**，身份由支付渠道识别
- 微信：`openid`（公众号 OAuth / JSAPI）
- 支付宝：回调 `buyer_id`
- 开发测试：`fake` 渠道生成 UUID
- 订单/支付记录关联三方用户，管理后台与 App 展示代付人信息

**交付物：**

- Migration：`external_users` 表；替换 `orders.paid_by_user_id` / `payments.payer_user_id`
- DDD：`ExternalUser` 实体、仓储、`UpsertExternalUserHandler`
- 改造 M07 代付 API（公开 pay、去掉 auth）
- Frontend H5 去掉登录 UI
- Frontend / App 类型与展示字段更新
- Unit + Feature 测试

**非目标（本期不做）：**

- 微信公众号 OAuth H5 完整实现（fake 通道先验收，OAuth 留 M16）
- 代付人管理后台 CRUD
- 跨渠道同人合并（微信 openid 与支付宝 buyer_id 视为不同人）
- 并发代付抢锁（M07 follow-up）

---

## 3. 设计决策摘要

| 决策 | 选择 | 理由 |
|------|------|------|
| 身份识别 | 纯支付渠道识别（方案 A） | 代付人非员工，不应走 Sanctum 登录 |
| 唯一标识 | `provider` + `external_id` 联合唯一 | 可扩展新渠道，规范清晰 |
| 字段迁移 | 直接替换 FK | 开发阶段，seed 可重建，避免双字段歧义 |
| 写入时机 | 发起支付 upsert + 回调补全 | 微信/fake 发起时有 openid；支付宝仅回调有 buyer_id |
| API 响应 | `paid_by_user` → `paid_by_payer` | 语义准确，含 provider 信息 |
| 代付 pay 路由 | 移出 `auth:sanctum` | 公开 token 校验即可 |

---

## 4. 业务流程

```
1. 员工 A 创建订单 payment_method=proxy
2. A 调用 POST /orders/{id}/proxy-pay-link → { url, token, expires_at }
3. A 分享 url 给外部联系人 B
4. B 打开 GET /proxy-pay/{token} 预览（公开，无需登录）
5. B 发起支付 POST /proxy-pay/{token}/pay（公开）
   ├─ wechat: body { channel: wechat, openid }（OAuth 前置，M16）
   ├─ alipay: body { channel: alipay } → 跳转 → 回调解析 buyer_id
   └─ fake:   body { channel: fake, payer_name? } → 后端生成 UUID external_id
6. UpsertExternalUser(provider, external_id, name?)
7. 创建 payment.payer_external_user_id = B
8. 支付回调 ConfirmPaymentHandler → orders.paid_by_external_user_id = B
9. 管理后台 / App 订单详情展示 paid_by_payer
```

---

## 5. 数据模型

### 5.1 新表 `external_users`

| 列 | 类型 | 说明 |
|----|------|------|
| `id` | bigint PK | |
| `provider` | varchar(20) | `wechat` / `alipay` / `fake` |
| `external_id` | varchar(128) | 渠道唯一标识 |
| `name` | varchar(100) nullable | 昵称/姓名 |
| `phone` | varchar(11) nullable | 通常为空 |
| `created_at` / `updated_at` | timestamps | |

**约束：** `UNIQUE(provider, external_id)`

### 5.2 字段替换

| 表 | 删除 | 新增 |
|----|------|------|
| `orders` | `paid_by_user_id` FK → users | `paid_by_external_user_id` FK → external_users nullable |
| `payments` | `payer_user_id` FK → users | `payer_external_user_id` FK → external_users nullable |

自付订单：`paid_by_external_user_id` 与 `payer_external_user_id` 均为 `NULL`（下单人在 `orders.user_id`）。

### 5.3 各渠道 external_id 规则

| provider | external_id 来源 | name 来源 |
|----------|------------------|-----------|
| `wechat` | 公众号 OAuth `openid` | OAuth 昵称（可选） |
| `alipay` | 回调 `buyer_id` | 回调买家昵称（若有） |
| `fake` | 后端 `Str::uuid()`；同浏览器 localStorage 复用 | 请求体 `payer_name`（可选） |

---

## 6. Backend 架构

### 6.1 目录结构

```
backend/app/
├── Domain/ExternalUser/
│   ├── Entities/ExternalUser.php
│   ├── Repositories/ExternalUserRepositoryInterface.php
│   └── ValueObjects/ExternalUserProvider.php
├── Application/ExternalUser/
│   └── UpsertExternalUser/UpsertExternalUserHandler.php
├── Infrastructure/Persistence/Eloquent/
│   ├── Models/ExternalUserModel.php
│   └── EloquentExternalUserRepository.php
```

### 6.2 改动点

| 组件 | 变更 |
|------|------|
| `InitiateProxyPaymentHandler` | 去掉 `$payerUserId`；接收 provider/external_id/name → upsert → 写 payment |
| `ConfirmPaymentHandler` | 从回调解析 payer（alipay buyer_id）；upsert；写 order |
| `MarkOrderPaidHandler` | `paidByUserId` → `paidByExternalUserId` |
| `Order` 实体 / 仓储 | 字段重命名；join external_users 取展示名 |
| `Payment` 实体 / 仓储 | `payerUserId` → `payerExternalUserId` |
| `routes/api.php` | `POST /proxy-pay/{token}/pay` 移出 auth 组 |
| `ProxyPayController::pay` | 不再读 `$request->user()` |
| `InitiateProxyPaymentRequest` | 新增 `provider`/`external_id`/`payer_name`；fake 时 external_id 可选 |

### 6.3 API 响应变更

**订单详情（Catalog + Admin）：**

```json
{
  "paid_by_payer": {
    "id": 1,
    "name": "张三",
    "phone": null,
    "provider": "wechat"
  }
}
```

代付订单且未支付时省略 `paid_by_payer`。无 `name` 时前端展示「微信用户」或脱敏 `external_id` 后四位。

---

## 7. Frontend / App

| 端 | 变更 |
|----|------|
| **H5 `/proxy-pay/:token`** | 移除员工登录表单；dev 环境直接「确认代付」；localStorage 存 fake external_id |
| **管理后台** | `paid_by_user` → `paid_by_payer`；列表/Drawer 展示 |
| **App** | 类型与 OrderDetailScreen 字段更新 |

---

## 8. 测试

| 类型 | 覆盖 |
|------|------|
| Unit | `UpsertExternalUserHandler` 新建与同 provider+id 更新 name |
| Unit | `ExternalUserProvider` 枚举 |
| Feature | 代付 pay **无需** Sanctum token |
| Feature | fake 全链路 → `paid_by_external_user_id` 有值 |
| Feature | 同一 external_id 二次代付复用记录 |
| Feature | Admin/App 订单详情返回 `paid_by_payer` |
| Feature | 代付订单自付仍 42204 |

**验证命令：**

```bash
./scripts/docker-test.sh --filter=ExternalUser
./scripts/docker-test.sh --filter=ProxyPayApiTest
./scripts/docker-test.sh
cd frontend && npm run build
```

---

## 9. 迁移与 Seed

- 新 migration 创建 `external_users`，修改 `orders`/`payments` FK
- `OrderSeeder` / `OrderFactory` 改用 `ExternalUserModel` 或 factory
- 现有 Navicat 中 `paid_by_user_id=11` 的演示数据可在 seed 时重建

---

## 10. 与 M07 关系

本 spec ** supersede ** M07 中以下决策：

| M07 原决策 | 新决策 |
|------------|--------|
| 代付需登录 + password.changed | 代付 pay 公开，token 校验 |
| paid_by_user_id → users | paid_by_external_user_id → external_users |
| 付款人必须是系统员工 | 付款人为三方外部用户 |

M07 其余部分（token 生成、预览、自付拦截、H5 路由）保持不变。
