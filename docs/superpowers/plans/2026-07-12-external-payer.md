# 代付三方用户（External Payer）Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 引入 `external_users` 三方付款人表，替换 `paid_by_user_id`/`payer_user_id`，代付 H5 无需员工登录，支付渠道识别付款人身份。

**Architecture:** 独立 `ExternalUser` 限界上下文；`UpsertExternalUserHandler` 在代付发起/回调时写入；M07 代付 pay 路由改为公开；Frontend/App 响应字段 `paid_by_user` → `paid_by_payer`。

**Tech Stack:** PHP **8.4** · Laravel **12** · MySQL **8.0** · React **18.3** · React Native **0.76.9**

**Spec:** [2026-07-12-external-payer-design.md](../specs/2026-07-12-external-payer-design.md)

## Global Constraints

- API 前缀 `/api/v1/`，响应 `{ "code": 0, "message": "ok", "data": {} }`
- DDD 分层 + Unit/Feature 测试；完成前 `./scripts/docker-test.sh` 全绿
- 禁止 `migrate:fresh` 等对 Docker MySQL `king_shop` 的破坏性命令
- 测试仅用 sqlite `:memory:`（通过 `./scripts/docker-test.sh`）
- PHP **8.4** · Laravel **12** · React **18.3**（版本锁定）

---

### Task 1: Migration + ExternalUser Domain

**Files:**
- Create: `backend/database/migrations/2026_07_12_231000_create_external_users_and_replace_payer_fks.php`
- Create: `backend/app/Domain/ExternalUser/ValueObjects/ExternalUserProvider.php`
- Create: `backend/app/Domain/ExternalUser/Entities/ExternalUser.php`
- Create: `backend/app/Domain/ExternalUser/Repositories/ExternalUserRepositoryInterface.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/ExternalUserModel.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentExternalUserRepository.php`
- Create: `backend/database/factories/ExternalUserFactory.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` — bind repository

**Interfaces:**
- Produces: `ExternalUserRepositoryInterface::findByProviderAndExternalId(string $provider, string $externalId): ?ExternalUser`
- Produces: `ExternalUserRepositoryInterface::save(ExternalUser $user): ExternalUser`

- [ ] **Step 1: Write migration**

```php
// 2026_07_12_231000_create_external_users_and_replace_payer_fks.php
Schema::create('external_users', function (Blueprint $table) {
    $table->id();
    $table->string('provider', 20);
    $table->string('external_id', 128);
    $table->string('name', 100)->nullable();
    $table->string('phone', 11)->nullable();
    $table->timestamps();
    $table->unique(['provider', 'external_id']);
});

Schema::table('orders', function (Blueprint $table) {
    $table->dropConstrainedForeignId('paid_by_user_id');
    $table->foreignId('paid_by_external_user_id')->nullable()->constrained('external_users')->nullOnDelete();
});

Schema::table('payments', function (Blueprint $table) {
    $table->dropConstrainedForeignId('payer_user_id');
    $table->foreignId('payer_external_user_id')->nullable()->constrained('external_users')->nullOnDelete();
});
```

- [ ] **Step 2: Write ExternalUserProvider value object**

```php
final class ExternalUserProvider
{
    public const WECHAT = 'wechat';
    public const ALIPAY = 'alipay';
    public const FAKE = 'fake';

    public static function fromString(string $value): self { /* validate in: wechat,alipay,fake */ }
    public function value(): string { return $this->value; }
}
```

- [ ] **Step 3: Write ExternalUser entity + repository + Eloquent impl + factory**
- [ ] **Step 4: Register `ExternalUserRepositoryInterface` in AppServiceProvider**
- [ ] **Step 5: Run migration in test**

Run: `./scripts/docker-test.sh --filter=HealthCheckTest`  
Expected: migration applies without error

---

### Task 2: UpsertExternalUserHandler (TDD)

**Files:**
- Create: `backend/tests/Unit/Application/ExternalUser/UpsertExternalUserHandlerTest.php`
- Create: `backend/app/Application/ExternalUser/UpsertExternalUser/UpsertExternalUserHandler.php`

**Interfaces:**
- Consumes: `ExternalUserRepositoryInterface`
- Produces: `UpsertExternalUserHandler::handle(ExternalUserProvider $provider, string $externalId, ?string $name = null, ?string $phone = null): ExternalUser`

- [ ] **Step 1: Write failing test — creates new user**

```php
public function test_creates_external_user_when_not_exists(): void
{
    $repo = $this->createMock(ExternalUserRepositoryInterface::class);
    $repo->method('findByProviderAndExternalId')->willReturn(null);
    $repo->expects($this->once())->method('save')->willReturnCallback(fn ($u) => new ExternalUser(
        id: 1,
        provider: ExternalUserProvider::fromString('fake'),
        externalId: 'uuid-1',
        name: '测试代付人',
        phone: null,
        createdAt: new \DateTimeImmutable,
        updatedAt: new \DateTimeImmutable,
    ));

    $handler = new UpsertExternalUserHandler($repo);
    $result = $handler->handle(
        ExternalUserProvider::fromString('fake'),
        'uuid-1',
        '测试代付人',
    );

    $this->assertSame(1, $result->id);
    $this->assertSame('测试代付人', $result->name);
}
```

- [ ] **Step 2: Write failing test — updates name on existing**

```php
public function test_updates_name_when_user_already_exists(): void
{
    $existing = new ExternalUser(
        id: 5,
        provider: ExternalUserProvider::fromString('wechat'),
        externalId: 'oABC',
        name: null,
        phone: null,
        createdAt: new \DateTimeImmutable,
        updatedAt: new \DateTimeImmutable,
    );
    // repo returns $existing, save called with name='李四'
    // assert result name === '李四' and id === 5
}
```

- [ ] **Step 3: Run tests — expect FAIL**

Run: `./scripts/docker-test.sh --filter=UpsertExternalUserHandlerTest`  
Expected: FAIL — class not found

- [ ] **Step 4: Implement UpsertExternalUserHandler (find → merge name/phone → save)**
- [ ] **Step 5: Run tests — expect PASS**

Run: `./scripts/docker-test.sh --filter=UpsertExternalUserHandlerTest`  
Expected: 2 passed

---

### Task 3: Order + Payment 实体与仓储重命名

**Files:**
- Modify: `backend/app/Domain/Order/Entities/Order.php` — `paidByUserId` → `paidByExternalUserId`; `paidByUserName` → `paidByPayerName`; drop department
- Modify: `backend/app/Domain/Payment/Entities/Payment.php` — `payerUserId` → `payerExternalUserId`
- Modify: `backend/app/Infrastructure/Persistence/Eloquent/Models/OrderModel.php` — relation `paidByExternalUser()`
- Modify: `backend/app/Infrastructure/Persistence/Eloquent/Models/PaymentModel.php`
- Modify: `backend/app/Infrastructure/Persistence/Eloquent/EloquentOrderRepository.php`
- Modify: `backend/app/Infrastructure/Persistence/Eloquent/EloquentPaymentRepository.php`
- Modify: `backend/app/Application/Order/MarkOrderPaid/MarkOrderPaidHandler.php`
- Modify: `backend/database/factories/OrderFactory.php`
- Modify: `backend/database/seeders/OrderSeeder.php`

**Interfaces:**
- Produces: `Order::$paidByExternalUserId`, `Order::$paidByPayerName`, `Order::$paidByPayerPhone`
- Produces: `Payment::$payerExternalUserId`
- Produces: `MarkOrderPaidHandler::handle(int $orderId, ?int $paidByExternalUserId = null, ...)`

- [ ] **Step 1: Rename fields across Domain + Infrastructure (grep `paidByUserId` / `payerUserId` — fix all)**
- [ ] **Step 2: OrderModel join external_users for payer name in list/detail queries**
- [ ] **Step 3: Update OrderFactory — `paid_by_external_user_id` via ExternalUserFactory**
- [ ] **Step 4: Update OrderSeeder — create ExternalUser for proxy demo order**
- [ ] **Step 5: Fix compile errors**

Run: `./scripts/docker-test.sh --filter=OrderHandlersTest`  
Expected: tests pass or reveal remaining rename sites

---

### Task 4: InitiateProxyPayment — 公开 + upsert payer

**Files:**
- Modify: `backend/routes/api.php` — move `POST /proxy-pay/{token}/pay` outside auth group
- Modify: `backend/app/Http/Controllers/Catalog/ProxyPayController.php`
- Modify: `backend/app/Http/Requests/Catalog/InitiateProxyPaymentRequest.php`
- Modify: `backend/app/Application/ProxyPay/InitiateProxyPayment/InitiateProxyPaymentHandler.php`
- Modify: `backend/app/Application/Payment/ConfirmPayment/ConfirmPaymentHandler.php`

**Interfaces:**
- Consumes: `UpsertExternalUserHandler::handle(ExternalUserProvider, string $externalId, ?string $name): ExternalUser`
- Produces: `InitiateProxyPaymentHandler::handle(string $token, ExternalUserProvider $provider, string $externalId, ?string $payerName = null, ?string $openid = null, ?string $channel = null): array`

- [ ] **Step 1: Update InitiateProxyPaymentRequest validation**

```php
'channel' => ['nullable', Rule::in(PaymentChannelPolicy::proxyPayChannels())],
'provider' => ['required', Rule::in(['wechat', 'alipay', 'fake'])],
'external_id' => ['nullable', 'string', 'max:128'], // fake 可空，后端生成 UUID
'payer_name' => ['nullable', 'string', 'max:100'],
'openid' => ['nullable', 'string', 'max:128'], // wechat JSAPI
```

- [ ] **Step 2: ProxyPayController — resolve provider/external_id**

```php
$provider = ExternalUserProvider::fromString($validated['provider']);
$externalId = $validated['external_id']
    ?? ($provider->value() === 'fake' ? (string) Str::uuid() : null);
if ($externalId === null) {
    throw ValidationException::withMessages(['external_id' => '缺少付款人标识']);
}
$payer = $upsertHandler->handle($provider, $externalId, $validated['payer_name'] ?? null);
$result = $handler->handle($token, $payer->id, $validated['openid'] ?? null, $validated['channel'] ?? null);
```

- [ ] **Step 3: InitiateProxyPaymentHandler — accept `$payerExternalUserId` instead of `$payerUserId`**
- [ ] **Step 4: ConfirmPaymentHandler — `paidByExternalUserId ?? payment.payerExternalUserId`**
- [ ] **Step 5: Move route in api.php**

```php
Route::post('/proxy-pay/{token}/pay', [ProxyPayController::class, 'pay']); // 公开，auth 组外

Route::middleware(['auth:sanctum', 'password.changed'])->group(function (): void {
    // ... 不含 proxy-pay pay
});
```

---

### Task 5: API Resources + 测试更新

**Files:**
- Modify: `backend/app/Http/Resources/Catalog/OrderResource.php`
- Modify: `backend/app/Http/Resources/Admin/OrderResource.php`
- Modify: `backend/tests/Feature/Catalog/ProxyPayApiTest.php`
- Modify: `backend/tests/Feature/Admin/OrderApiTest.php`
- Modify: `backend/tests/Feature/Catalog/OrderApiTest.php` (if exists)

- [ ] **Step 1: Replace `paid_by_user` with `paid_by_payer` in resources**

```php
if ($order->paidByExternalUserId !== null) {
    $data['paid_by_payer'] = [
        'id' => $order->paidByExternalUserId,
        'name' => $order->paidByPayerName,
        'phone' => $order->paidByPayerPhone,
        'provider' => $order->paidByPayerProvider ?? null,
    ];
}
```

- [ ] **Step 2: Rewrite ProxyPayApiTest — no auth on pay**

```php
public function test_proxy_pay_without_login(): void
{
    $buyer = User::factory()->create();
    $order = OrderModel::factory()->for($buyer, 'user')->proxy()->create(['status' => 'pending_payment']);
    $token = ProxyPayTokenModel::factory()->for($order)->create();

    $this->postJson("/api/v1/proxy-pay/{$token->token}/pay", [
        'channel' => 'fake',
        'provider' => 'fake',
        'payer_name' => '外部代付人',
    ])->assertOk();

    // simulate notify ...
    $order->refresh();
    $this->assertNotNull($order->paid_by_external_user_id);
    $this->assertDatabaseHas('external_users', [
        'provider' => 'fake',
        'name' => '外部代付人',
    ]);
}
```

- [ ] **Step 3: Update Admin OrderApiTest — `paid_by_payer.name`**
- [ ] **Step 4: Run feature tests**

Run: `./scripts/docker-test.sh --filter=ProxyPayApiTest`  
Expected: all passed

Run: `./scripts/docker-test.sh --filter=OrderApiTest`  
Expected: all passed

---

### Task 6: Frontend H5 + 管理后台

**Files:**
- Modify: `frontend/src/pages/proxy-pay/ProxyPayPage.tsx` — remove login UI
- Modify: `frontend/src/api/proxyPay.ts` — pay payload with provider/external_id
- Modify: `frontend/src/types/order.ts` — `paid_by_payer`
- Modify: `frontend/src/components/OrderDetailDrawer.tsx`
- Modify: `frontend/src/pages/orders/OrderListPage.tsx`

- [ ] **Step 1: proxyPay.ts pay signature**

```typescript
pay(token: string, body: { channel?: string; provider: string; external_id?: string; payer_name?: string }) {
  return client.post(`/proxy-pay/${token}/pay`, body);
}
```

- [ ] **Step 2: ProxyPayPage — remove login; use localStorage fake payer id**

```typescript
const FAKE_PAYER_KEY = 'proxy_pay_fake_external_id';
function getOrCreateFakeExternalId(): string {
  let id = localStorage.getItem(FAKE_PAYER_KEY);
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem(FAKE_PAYER_KEY, id);
  }
  return id;
}

const handlePay = async () => {
  const external_id = getOrCreateFakeExternalId();
  const result = await proxyPayApi.pay(token, { channel: 'fake', provider: 'fake', external_id, payer_name: 'H5代付人' });
  // ... notify ...
};
```

- [ ] **Step 3: Update order types and admin list/drawer `paid_by_payer`**
- [ ] **Step 4: Build frontend**

Run: `cd frontend && npm run build`  
Expected: build success

---

### Task 7: App 订单展示

**Files:**
- Modify: `app/src/types/order.ts`
- Modify: `app/src/screens/OrderDetailScreen.tsx`
- Modify: `app/__tests__/api/orders.test.ts` (if mocks reference paid_by_user)

- [ ] **Step 1: Type `paid_by_payer?: { id: number; name: string; provider?: string }`**
- [ ] **Step 2: OrderDetailScreen label「代付人」读 `order.paid_by_payer?.name`**

---

### Task 8: 全量验证 + 文档

**Files:**
- Modify: `docs/superpowers/specs/2026-07-12-M07-proxy-pay-design.md` — 添加 superseded 注记
- Create: `docs/superpowers/records/2026-07-12-external-payer.md`

- [ ] **Step 1: Full docker test**

Run: `./scripts/docker-test.sh`  
Expected: all tests pass

- [ ] **Step 2: Update M07 spec with link to external-payer spec**
- [ ] **Step 3: Write execution record with acceptance checklist**

---

## 验证命令

```bash
./scripts/dev-up.sh
./scripts/docker-test.sh --filter=UpsertExternalUserHandlerTest
./scripts/docker-test.sh --filter=ProxyPayApiTest
./scripts/docker-test.sh
cd frontend && npm run build
```

## 完成检查清单

- [ ] `external_users` 表 + FK 替换完成
- [ ] `UpsertExternalUserHandler` Unit 测试通过
- [ ] 代付 pay 无需 Sanctum；fake 全链路写入 `paid_by_external_user_id`
- [ ] API 返回 `paid_by_payer`
- [ ] H5 无登录 UI；管理后台/App 展示更新
- [ ] `./scripts/docker-test.sh` 全绿
- [ ] Spec + Plan + Record 文档齐全

## 后续（M16）

- [ ] H5 微信 OAuth 获取 openid
- [ ] 支付宝回调解析 buyer_id upsert
- [ ] JSAPI 真实调起（非 fake notify 模拟）
