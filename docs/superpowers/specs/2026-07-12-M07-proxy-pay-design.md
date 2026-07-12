# M07 — 找人代付 Design Spec

> **⚠️ 付款人记录已 superseded：** M07 原设计将代付人写入 `orders.paid_by_user_id`（员工 `users` 表），已由 [代付三方用户（External Payer）Design Spec](./2026-07-12-external-payer-design.md) 替换为 `external_users` + `paid_by_external_user_id`。M07 其余流程（token、预览、自付拦截、H5 路由）仍有效。

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **状态：** ✅ 已实现  
> **依赖：** M05 订单（已完成）、M06 支付网关（已完成）  
> **后续依赖方：** M09 App 下单与代付分享、M10 App 订单中心

---

## 1. 目标

实现 **找人代付** 全链路：下单人选 `payment_method=proxy` → 生成分享链接 → 同事登录后代付 → 记录实际付款人。

**交付物：**

- Migration: `proxy_pay_tokens`；`payments.payer_user_id`
- DDD 分层：ProxyPayToken 实体、仓储、用例
- API：生成链接、公开预览、代付人发起支付
- Frontend H5 落地页 `/proxy-pay/:token`
- 支付成功后写入 `orders.paid_by_user_id`
- Feature 测试

**非目标（本期不做）：**

- App 内 Share API 分享（M09/M10）
- H5 真实微信 JSAPI / OAuth openid（上线前 M16 配置）
- 独立轻量 H5 工程（当前复用 frontend 公开路由）
- 并发代付抢锁（last-writer-wins，follow-up）

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| Token 存储 | `proxy_pay_tokens` 表 | 可撤销、可过期、可复用未过期 token |
| 链接有效期 | `order.created_at + auto_cancel_minutes` | 与订单支付超时一致 |
| 自付拦截 | 代付订单禁止 `POST /orders/{id}/pay` | 42204，引导走代付链接 |
| 付款人记录 | `payments.payer_user_id` → `orders.paid_by_user_id` | 支付层记录发起人，订单层展示 |
| 预览页 | 公开 GET，无需登录 | 降低分享门槛 |
| 代付支付 | 需登录 + `password.changed` | 付款人必须是系统员工 |
| H5 宿主 | frontend 公开路由 | 与 internal-mall §9 默认决策一致，降低耦合 |
| 开发联调 | H5 + API 支持 `channel=fake` | 无微信环境可验收 |

---

## 3. 业务流程

```
1. 员工 A 创建订单 payment_method=proxy
2. A 调用 POST /orders/{id}/proxy-pay-link → { url, token, expires_at }
3. A 分享 url 给同事 B（App 分享 → M09）
4. B 打开 GET /proxy-pay/{token} 预览（公开）
5. B 登录后 POST /proxy-pay/{token}/pay（JSAPI / fake）
6. 微信/支付宝回调 → ConfirmPaymentHandler
7. orders.paid_by_user_id = B，status = paid
8. 管理后台 / App 订单详情展示代付人（M13 字段已有）
```

---

## 4. 数据模型

### 4.1 `proxy_pay_tokens`

| 列 | 类型 | 说明 |
|---|---|---|
| `id` | bigint PK | |
| `order_id` | FK orders | |
| `token` | varchar(64) unique | 48 字符随机串 |
| `expires_at` | timestamp | 过期时间 |
| `created_at` / `updated_at` | timestamps | |

### 4.2 `payments.payer_user_id`（M07 追加）

| 列 | 类型 | 说明 |
|---|---|---|
| `payer_user_id` | FK users nullable | 代付场景为付款同事；自付为空 |

---

## 5. Backend 架构

### 5.1 目录结构

```
backend/app/
├── Domain/ProxyPay/
│   ├── Entities/ProxyPayToken.php
│   ├── Repositories/ProxyPayTokenRepositoryInterface.php
│   └── Exceptions/
│       ├── ProxyPayTokenNotFoundException.php      (404)
│       ├── ProxyPayLinkExpiredException.php        (42205)
│       └── OrderNotProxyPayException.php           (42206)
├── Application/ProxyPay/
│   ├── GenerateProxyPayLink/GenerateProxyPayLinkHandler.php
│   ├── GetProxyPayPreview/GetProxyPayPreviewHandler.php
│   └── InitiateProxyPayment/InitiateProxyPaymentHandler.php
├── Infrastructure/ProxyPay/
│   └── ProxyPayExpiryCalculator.php
└── Http/
    ├── Controllers/Catalog/ProxyPayController.php
    └── Requests/Catalog/InitiateProxyPaymentRequest.php
```

### 5.2 API

#### 公开

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/proxy-pay/{token}` | 代付预览 |

**响应示例：**

```json
{
  "code": 0,
  "data": {
    "order_no": "KS202607121430001",
    "total_amount": 3000,
    "status": "pending_payment",
    "buyer_name": "张三",
    "expires_at": "2026-07-12T15:00:00+08:00",
    "payable": true
  }
}
```

#### 员工（`auth:sanctum` + `password.changed`）

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/v1/orders/{id}/proxy-pay-link` | 生成链接（仅订单本人） |
| POST | `/api/v1/proxy-pay/{token}/pay` | 代付人发起支付 |

**生成链接响应：**

```json
{
  "code": 0,
  "data": {
    "url": "http://localhost:5173/proxy-pay/{token}",
    "token": "...",
    "expires_at": "2026-07-12T15:00:00+08:00"
  }
}
```

**代付支付请求体（可选）：**

```json
{ "channel": "wechat", "openid": "oXXXX" }
```

### 5.3 链接生成规则

- 仅 `payment_method=proxy` 且 `status=pending_payment` 的订单
- 若已有未过期 token → 复用，不重复创建
- URL = `{FRONTEND_URL}/proxy-pay/{token}`（`config('app.frontend_url')`）

### 5.4 代付支付

- `InitiateProxyPaymentHandler` 创建/更新 pending Payment，写入 `payer_user_id`
- 网关 `createPayment` 使用 `trade_type: JSAPI` + `openid`
- 禁止订单本人代付自己（可选 follow-up；当前允许任何登录员工代付）

---

## 6. Frontend 架构

### 6.1 路由

| 路径 | 组件 | 鉴权 |
|------|------|------|
| `/proxy-pay/:token` | ProxyPayPage | 公开（支付步骤需登录） |

### 6.2 ProxyPayPage 流程

1. `proxyPayApi.preview(token)` 加载订单摘要
2. 未登录 → 页内手机号密码登录（或跳转 `/login`）
3. 已登录 →「确认代付」调用 `proxyPayApi.pay(token, { channel: 'fake' })`
4. 开发环境：页面模拟 notify 完成闭环
5. 刷新 preview 展示最新状态

### 6.3 文件

```
frontend/src/
├── api/proxyPay.ts
└── pages/proxy-pay/ProxyPayPage.tsx
```

---

## 7. 错误码

| code | 异常 | 场景 |
|------|------|------|
| 404 | `ProxyPayTokenNotFoundException` | token 无效 |
| 42204 | `OrderNotPayableException` | 订单不可支付 / 代付订单自付 |
| 42205 | `ProxyPayLinkExpiredException` | 链接过期 |
| 42206 | `OrderNotProxyPayException` | 非代付订单请求生成链接 |
| 403 | `OrderAccessDeniedException` | 非本人生成链接 |

---

## 8. 与 M06 / M13 集成

| 模块 | 集成点 |
|------|--------|
| M06 | 复用 `ConfirmPaymentHandler`、`PaymentGatewayInterface` JSAPI options |
| M06 | `InitiatePaymentHandler` 拦截 proxy 订单自付 |
| M13 | 管理后台列表/Drawer 已有 `paid_by_user` 展示 |
| M05 | 员工订单 API 返回 `paid_by_user` |

---

## 9. 测试策略

| 类型 | 文件 | 覆盖 |
|------|------|------|
| Feature | `ProxyPayApiTest` | 生成链接、拦截自付、预览、过期、代付+notify |
| Feature | `PaymentApiTest` | 代付订单自付 42204 |
| Feature | `Admin/OrderApiTest` | 代付订单详情含 paid_by_user |

**完成门槛：** `./scripts/docker-test.sh --filter=ProxyPayApiTest` 全绿；`npm run build` 通过

---

## 10. 验收标准

- [x] 下单人可不付款，通过 API 生成代付链接
- [x] 公开预览展示订单号、金额、下单人、有效期
- [x] 代付人登录后可发起支付（dev fake 通道全链路）
- [x] 支付成功后 `orders.paid_by_user_id` 为付款人
- [x] 链接过期返回 42205
- [x] 代付订单无法 `/orders/{id}/pay` 自付
- [x] 管理后台展示代付人（M13）
- [x] Frontend `/proxy-pay/:token` 可访问

---

## 11. 已知限制与后续

| 项 | 说明 | 归属 |
|----|------|------|
| App 分享链接 | RN Share API | M09/M10 |
| 微信 JSAPI + openid | H5 OAuth | 上线前 |
| 独立 H5 工程 | 可选拆分 | 优化 |
| 并发代付 | payer 覆盖 | follow-up |

---

## 12. 预估

**1 天**

| 任务 | 预估 |
|------|------|
| Migration + Domain + Repository | 2h |
| 三个 Handler + API | 2h |
| 网关 JSAPI 扩展 + ConfirmPayment 联动 | 1h |
| Frontend H5 + 测试 | 2h |

---

## 13. 关联文档

- 总览：[2026-07-12-internal-mall-design.md](./2026-07-12-internal-mall-design.md) § M07
- 实施计划：[2026-07-12-M07-proxy-pay.md](../plans/2026-07-12-M07-proxy-pay.md)
- 执行记录：[2026-07-12-M07-proxy-pay.md](../records/2026-07-12-M07-proxy-pay.md)
- 前置：[2026-07-12-M06-payment-integration-design.md](./2026-07-12-M06-payment-integration-design.md)
