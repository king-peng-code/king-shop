# M06 支付对接 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.  
> **Status:** ✅ 已实现（2026-07-12，commit `174fe46` + review fix `40db545`）

**Goal:** 实现支付网关抽象、支付宝沙箱/微信/fake 三通道、员工发起支付、异步回调、主动查询补偿。

**Architecture:** Domain `PaymentGatewayInterface` → Infrastructure 网关实现 → Application Handler 编排 → Http 薄控制器。`ConfirmPaymentHandler` 幂等确认并调用 `MarkOrderPaidHandler`。

**Tech Stack:** PHP **8.4** · Laravel **12** · MySQL **8.0** · PHPUnit 11

**Spec:** [2026-07-12-M06-payment-integration-design.md](../specs/2026-07-12-M06-payment-integration-design.md)

## Global Constraints

- PHP **8.4** / Laravel **12**（Docker 内测试：`./scripts/docker-test.sh`）
- API 前缀 `/api/v1/`，统一响应格式
- DDD 分层 + TDD
- 支付配置从 M01 `system_configs.payment.*` 读取
- `FakePaymentGateway` 仅 `local`/`testing`（`PaymentChannelPolicy`）

---

### Task 1: Migration、实体与值对象

**Files:**
- Create: `backend/database/migrations/2026_07_12_210000_create_payments_table.php`
- Create: `backend/app/Domain/Payment/Entities/Payment.php`
- Create: `backend/app/Domain/Payment/ValueObjects/PaymentChannel.php`
- Create: `backend/app/Domain/Payment/ValueObjects/PaymentStatus.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/PaymentModel.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentPaymentRepository.php`
- Create: `backend/database/factories/PaymentFactory.php`
- Create: `backend/tests/Unit/Domain/Payment/ValueObjects/PaymentStatusTest.php`

- [x] **Step 1:** 写 failing `PaymentStatusTest`
- [x] **Step 2:** `./scripts/docker-test.sh --filter=PaymentStatusTest` → FAIL
- [x] **Step 3:** 实现 migration + 值对象 + Model + Repository
- [x] **Step 4:** 测试绿色；在 `AppServiceProvider` 绑定 `PaymentRepositoryInterface`

---

### Task 2: 网关接口与 DTO

**Files:**
- Create: `backend/app/Domain/Payment/Services/PaymentGatewayInterface.php`
- Create: `backend/app/Domain/Payment/Services/PaymentGatewayResolverInterface.php`
- Create: `backend/app/Domain/Payment/DTO/PaymentCreateResult.php`
- Create: `backend/app/Domain/Payment/DTO/NotifyVerifyResult.php`
- Create: `backend/app/Domain/Payment/DTO/PaymentQueryResult.php`
- Create: `backend/app/Infrastructure/Payment/PaymentConfigReader.php`
- Create: `backend/app/Infrastructure/Payment/ConfigPaymentGatewayResolver.php`
- Create: `backend/app/Infrastructure/Payment/OutTradeNoGenerator.php`

- [x] **Step 1:** 定义接口与 DTO
- [x] **Step 2:** `ConfigPaymentGatewayResolver` match channel → gateway
- [x] **Step 3:** `PaymentConfigReader` 读 `payment.provider`

---

### Task 3: 网关实现（Alipay / WeChat / Fake）

**Files:**
- Create: `backend/app/Infrastructure/Payment/Gateways/AlipaySandboxGateway.php`
- Create: `backend/app/Infrastructure/Payment/Gateways/WechatPayGateway.php`
- Create: `backend/app/Infrastructure/Payment/Gateways/FakePaymentGateway.php`
- Create: `backend/app/Infrastructure/Payment/Signature/AlipaySigner.php`
- Create: `backend/app/Infrastructure/Payment/Signature/WechatSigner.php`
- Create: `backend/tests/Unit/Infrastructure/Payment/Signature/AlipaySignerTest.php`
- Create: `backend/tests/Unit/Infrastructure/Payment/Signature/WechatSignerTest.php`

- [x] **Step 1:** `FakePaymentGateway` — create / verifyNotify / query（测试优先）
- [x] **Step 2:** `AlipaySandboxGateway` — 沙箱预下单 + RSA 验签
- [x] **Step 3:** `WechatPayGateway` — 统一下单 + MD5/HMAC 验签
- [x] **Step 4:** Signer 单元测试通过

---

### Task 4: InitiatePayment + ConfirmPayment

**Files:**
- Create: `backend/app/Application/Payment/InitiatePayment/InitiatePaymentHandler.php`
- Create: `backend/app/Application/Payment/ConfirmPayment/ConfirmPaymentHandler.php`
- Create: `backend/app/Http/Requests/Catalog/InitiatePaymentRequest.php`
- Create: `backend/app/Http/Resources/Catalog/PaymentResource.php`
- Modify: `backend/app/Http/Controllers/Catalog/OrderController.php` — `pay` 方法
- Create: `backend/tests/Unit/Application/Payment/ConfirmPaymentHandlerTest.php`

- [x] **Step 1:** 写 failing `ConfirmPaymentHandlerTest`（幂等）
- [x] **Step 2:** 实现 `InitiatePaymentHandler`：校验订单归属/状态，创建 pending payment
- [x] **Step 3:** 实现 `ConfirmPaymentHandler`：事务内更新 payment + `MarkOrderPaidHandler`
- [x] **Step 4:** `OrderController::pay` + `PaymentResource`
- [x] **Step 5:** 注册路由 `POST /orders/{order}/pay`

---

### Task 5: 回调 Handler + Controller

**Files:**
- Create: `backend/app/Application/Payment/HandleAlipayNotify/HandleAlipayNotifyHandler.php`
- Create: `backend/app/Application/Payment/HandleWechatNotify/HandleWechatNotifyHandler.php`
- Create: `backend/app/Http/Controllers/PaymentNotifyController.php`
- Modify: `backend/routes/api.php` — 公开 notify 路由

- [x] **Step 1:** `HandleAlipayNotifyHandler` — 读 out_trade_no → 验签 → confirm
- [x] **Step 2:** `HandleWechatNotifyHandler` — XML 解析 + JSON fallback → 验签 → confirm
- [x] **Step 3:** `PaymentNotifyController` 返回平台期望格式
- [x] **Step 4:** Feature 测试 fake notify 全链路

---

### Task 6: 主动查询补偿 + 调度

**Files:**
- Create: `backend/app/Application/Payment/QueryPendingPayments/QueryPendingPaymentsHandler.php`
- Create: `backend/app/Console/Commands/QueryPendingPaymentsCommand.php`
- Create: `backend/tests/Unit/Application/Payment/QueryPendingPaymentsHandlerTest.php`
- Modify: `backend/routes/console.php` — Schedule 注册

- [x] **Step 1:** `QueryPendingPaymentsHandler` 扫描 pending → gateway.query → confirm
- [x] **Step 2:** `payments:query-pending` 命令
- [x] **Step 3:** `Schedule::command('payments:query-pending')->everyFiveMinutes()`

---

### Task 7: Feature 测试与验收

**Files:**
- Create: `backend/tests/Feature/Catalog/PaymentApiTest.php`

- [x] **Step 1:** 发起支付、fake notify、重复 notify、不可支付订单、create→pay→notify 全链路
- [x] **Step 2:** `./scripts/docker-test.sh --filter=PaymentApiTest` 全绿

---

### Task 8: 安全加固（Code Review follow-up）

**Files:**
- Create: `backend/app/Infrastructure/Payment/PaymentChannelPolicy.php`
- Modify: `InitiatePaymentRequest.php` — 动态 channel 白名单
- Modify: `HandleAlipayNotifyHandler.php`, `HandleWechatNotifyHandler.php` — fake guard

- [x] **Step 1:** `fake` 仅 local/testing 可选
- [x] **Step 2:** notify 拒绝生产环境 fake payment
- [x] **Step 3:** 全量 `./scripts/docker-test.sh` 179 passed

---

## 验证命令

```bash
./scripts/dev-up.sh
./scripts/docker-test.sh --filter=PaymentApiTest
./scripts/docker-test.sh --filter=ConfirmPaymentHandlerTest
```

## 完成检查清单

- [x] `payments` 表 migration
- [x] 三网关 + Resolver + ConfigReader
- [x] 发起支付 / 回调 / 主动查询
- [x] 定时调度已注册
- [x] Feature + Unit 测试
- [x] `PaymentChannelPolicy` 安全策略
