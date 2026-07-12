# M06 — 支付对接 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **状态：** ✅ 已实现  
> **依赖：** M01 系统配置（已完成）、M05 订单（已完成）  
> **后续依赖方：** M07 找人代付、M09 App 下单与支付

---

## 1. 目标

实现 **支付网关抽象层** 与 **员工自付** 全链路：发起支付 → 第三方回调 / 主动查询 → 订单状态 `pending_payment` → `paid`。

**交付物：**

- Migration: `payments`
- DDD 分层：Payment 实体、网关接口、仓储、用例
- 网关实现：`AlipaySandboxGateway`、`WechatPayGateway`、`FakePaymentGateway`（仅 local/testing）
- 员工 API：`POST /api/v1/orders/{id}/pay`
- 回调：`POST /api/v1/payments/notify/alipay`、`POST /api/v1/payments/notify/wechat`
- 定时任务：`payments:query-pending`（补偿回调失败）
- 支付配置从 M01 `system_configs`（`payment` 组）读取
- Unit + Feature 测试

**非目标（本期不做）：**

- App 端支付 UI（M09）
- 代付链接与 JSAPI（M07）
- 退款流程、`refunded` 状态业务
- 支付金额与 notify 金额的强校验（follow-up）
- 已取消订单收到 late notify 的对账策略（follow-up）

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| 网关模式 | `PaymentGatewayInterface` + Resolver | 支付宝沙箱先行，微信上线时切换配置即可 |
| 支付单持久化 | 独立 `payments` 表 | 支持重试、回调幂等、主动查询 |
| 渠道选择 | 默认读 `payment.provider`；客户端可传 `channel` | 演示期可指定沙箱；生产由配置控制 |
| 开发联调 | `FakePaymentGateway` 仅 `local`/`testing` | 无真实商户号时可跑通全链路 |
| 回调入口 | 独立 alipay / wechat URL | 符合各平台 notify 约定 |
| 确认支付 | `ConfirmPaymentHandler` 事务内更新 payment + order | 幂等、原子性 |
| 补偿机制 | `QueryPendingPaymentsHandler` + 定时调度 | 应对回调丢失 |

---

## 3. 支付流程

### 3.1 自付（`payment_method=self`）

```
员工 POST /orders/{id}/pay
  → InitiatePaymentHandler 创建/复用 pending Payment
  → Gateway.createPayment 返回 pay_params
  → 客户端调起支付宝 / 微信 SDK

第三方 POST /payments/notify/{channel}
  → Gateway.verifyNotify 验签
  → ConfirmPaymentHandler 更新 payment.success + order.paid

（补偿）Schedule: payments:query-pending
  → Gateway.queryPayment
  → 成功则 ConfirmPaymentHandler
```

### 3.2 与订单状态机

仅 `pending_payment` 订单可发起支付；确认成功后 `MarkOrderPaidHandler` 将订单转为 `paid`。

---

## 4. 数据模型

### 4.1 `payments`

| 列 | 类型 | 说明 |
|---|---|---|
| `id` | bigint PK | |
| `order_id` | FK orders | 关联订单 |
| `payer_user_id` | FK users nullable | 实际付款人（M07 代付写入） |
| `out_trade_no` | varchar(64) unique | 商户订单号 |
| `trade_no` | varchar(64) nullable | 第三方流水号 |
| `amount` | unsignedBigInteger | 金额（分） |
| `channel` | varchar(20) | `alipay_sandbox` / `wechat` / `fake` |
| `status` | varchar(20) | `pending` / `success` / `failed` |
| `paid_at` | timestamp nullable | |
| `raw_notify` | json nullable | 原始回调 payload |
| `created_at` / `updated_at` | timestamps | |

> `payer_user_id` 由 M07 migration 追加；M06 初始 migration 仅含核心字段。

---

## 5. Backend 架构

### 5.1 目录结构

```
backend/app/
├── Domain/Payment/
│   ├── Entities/Payment.php
│   ├── ValueObjects/PaymentChannel.php, PaymentStatus.php
│   ├── DTO/PaymentCreateResult.php, NotifyVerifyResult.php, PaymentQueryResult.php
│   ├── Services/PaymentGatewayInterface.php, PaymentGatewayResolverInterface.php
│   ├── Repositories/PaymentRepositoryInterface.php
│   └── Exceptions/*.php
├── Application/Payment/
│   ├── InitiatePayment/InitiatePaymentHandler.php
│   ├── ConfirmPayment/ConfirmPaymentHandler.php
│   ├── HandleAlipayNotify/HandleAlipayNotifyHandler.php
│   ├── HandleWechatNotify/HandleWechatNotifyHandler.php
│   └── QueryPendingPayments/QueryPendingPaymentsHandler.php
├── Infrastructure/Payment/
│   ├── Gateways/AlipaySandboxGateway.php, WechatPayGateway.php, FakePaymentGateway.php
│   ├── ConfigPaymentGatewayResolver.php, PaymentConfigReader.php
│   ├── PaymentChannelPolicy.php, OutTradeNoGenerator.php
│   └── Signature/AlipaySigner.php, WechatSigner.php
└── Http/
    ├── Controllers/Catalog/OrderController.php  (pay)
    ├── Controllers/PaymentNotifyController.php
    └── Resources/Catalog/PaymentResource.php
```

### 5.2 网关接口

```php
interface PaymentGatewayInterface {
    public function channel(): string;
    public function createPayment(Payment $payment, Order $order, array $options = []): PaymentCreateResult;
    public function queryPayment(string $outTradeNo): PaymentQueryResult;
    public function verifyNotify(Request $request): NotifyVerifyResult;
}
```

`createPayment` 的 `$options` 预留 JSAPI（M07）：`trade_type`、`openid`。

### 5.3 Catalog API

| 方法 | 路径 | 鉴权 | 说明 |
|------|------|------|------|
| POST | `/api/v1/orders/{id}/pay` | `auth:sanctum` + `password.changed` | 发起支付 |

**请求体（可选）：**

```json
{ "channel": "alipay_sandbox" }
```

**响应：**

```json
{
  "code": 0,
  "data": {
    "payment": {
      "id": 1,
      "order_id": 10,
      "out_trade_no": "KS202607121430001P001",
      "amount": 3000,
      "channel": "fake",
      "status": "pending"
    },
    "pay_params": { "channel": "fake", "out_trade_no": "..." }
  }
}
```

### 5.4 回调 API（公开，无 Sanctum）

| 方法 | 路径 | 响应 |
|------|------|------|
| POST | `/api/v1/payments/notify/alipay` | 文本 `success` / `failure` |
| POST | `/api/v1/payments/notify/wechat` | XML `SUCCESS` / `FAIL` |

Handler 根据 `out_trade_no` 查 payment，再按 **payment.channel** 选择网关验签。  
`PaymentChannelPolicy::assertNotifyAllowed` 拒绝非 testing 环境下的 `fake` 渠道 notify。

### 5.5 系统配置（M01）

| group | key | 说明 |
|-------|-----|------|
| `payment` | `provider` | 默认网关：`alipay_sandbox` / `wechat` / `fake`（仅 dev） |
| `payment` | `alipay.*` | 支付宝沙箱凭证 |
| `payment` | `wechat.*` | 微信 App / JSAPI 凭证 |

---

## 6. 错误码

| code | 异常 | 场景 |
|------|------|------|
| 404 | `OrderNotFoundException` | 订单不存在 |
| 403 | `OrderAccessDeniedException` | 非本人订单 |
| 42204 | `OrderNotPayableException` | 非待支付 / 代付订单走自付 |
| 404 | `PaymentNotFoundException` | 回调找不到 payment |
| 422 | `InvalidPaymentSignatureException` | 验签失败 / fake 渠道被禁 |
| 422 | `UnsupportedPaymentProviderException` | 未知 channel |

---

## 7. 安全策略

| 规则 | 实现 |
|------|------|
| `fake` 渠道仅 dev | `PaymentChannelPolicy::fakeAllowed()` → `local`/`testing` |
| 客户端 channel 白名单 | `InitiatePaymentRequest` 动态 Rule |
| Notify 拒绝 fake（生产） | `HandleAlipayNotify` / `HandleWechatNotify` 前置 guard |
| 回调验签 | 各 Gateway `verifyNotify` + Signer |

---

## 8. 定时任务

`routes/console.php`：

```php
Schedule::command('orders:cancel-expired')->everyMinute();
Schedule::command('payments:query-pending')->everyFiveMinutes();
```

| 命令 | 说明 |
|------|------|
| `php artisan payments:query-pending` | 扫描 pending payment，向网关主动查询并确认 |

---

## 9. 测试策略

| 类型 | 文件 | 覆盖 |
|------|------|------|
| Unit | `ConfirmPaymentHandlerTest` | 幂等确认 |
| Unit | `QueryPendingPaymentsHandlerTest` | 主动查询 |
| Unit | `AlipaySignerTest`, `WechatSignerTest` | 签名 |
| Feature | `PaymentApiTest` | 发起、notify、重复 notify、全链路 |
| Feature | `OrderApiTest` | 与订单联动 |

**完成门槛：** `./scripts/docker-test.sh` 全通过

---

## 10. 验收标准

- [x] 员工对待支付订单可发起支付，返回 `pay_params`
- [x] fake 通道：notify 后订单变 `paid`（testing 环境）
- [x] 重复 notify 不报错、不重复改状态
- [x] 非待支付订单发起支付返回 42204
- [x] 代付订单走 `/pay` 返回 42204（M07 拦截，M06 预留）
- [x] `payments:query-pending` 命令可执行
- [x] `./scripts/docker-test.sh --filter=PaymentApiTest` 全绿

---

## 11. 预估

**1.5 天**

| 任务 | 预估 |
|------|------|
| Migration + Domain + Repository | 2h |
| 三网关 + Signer | 4h |
| Handlers + Notify + Command | 3h |
| Feature/Unit 测试 | 2h |
| 安全加固 + 调度注册 | 1h |

---

## 12. 关联文档

- 总览：[2026-07-12-internal-mall-design.md](./2026-07-12-internal-mall-design.md) § M06
- 实施计划：[2026-07-12-M06-payment-integration.md](../plans/2026-07-12-M06-payment-integration.md)
- 后续：[2026-07-12-M07-proxy-pay-design.md](./2026-07-12-M07-proxy-pay-design.md)
