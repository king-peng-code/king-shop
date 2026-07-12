# M13 管理后台订单管理 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现管理后台订单列表/Drawer 详情/状态操作，并补齐 M05 Admin Order API（迁移、DDD、状态机、测试）。

**Architecture:** Backend 按 Domain → Application → Infrastructure → Http 分层；`OrderStateMachine` 集中校验状态转换；Admin 状态变更用独立 POST 端点。Frontend 复用 M11/M12 的 Table + Drawer 模式，对接 `/api/v1/admin/orders`。

**Tech Stack:** React **18.3.1** · TypeScript **~5.6** · Vite **5.x** · Ant Design **5.x** · Laravel **12** · PHP **8.4** · MySQL **8.0**

## Global Constraints

- React **18.3.1**（禁止 17 / 18.0~18.2 / 19）
- PHP **8.4** / Laravel **12**（Backend 测试在 Docker 内：`./scripts/docker-test.sh`）
- API 前缀 `/api/v1/`，响应 `{ "code": 0, "message": "ok", "data": {} }`
- 金额整数分存储；前端 `fenToYuan()` 展示
- DDD 分层 + TDD；无测试不得合并
- Frontend 不直连数据库；API 放 `frontend/src/api/`
- 假定 M11/M12 已合并（AdminLayout、AuthContext、`api/client.ts`、`fenToYuan`、`resolveMediaUrl` 可用）

---

### Task 1: 数据库迁移与值对象（TDD）

**Files:**
- Create: `backend/database/migrations/2026_07_12_200000_create_orders_table.php`
- Create: `backend/database/migrations/2026_07_12_200100_create_order_items_table.php`
- Create: `backend/app/Domain/Order/ValueObjects/OrderStatus.php`
- Create: `backend/app/Domain/Order/ValueObjects/PaymentMethod.php`
- Create: `backend/tests/Unit/Domain/Order/ValueObjects/OrderStatusTest.php`
- Create: `backend/tests/Unit/Domain/Order/ValueObjects/PaymentMethodTest.php`

**Interfaces:**
- Produces: `OrderStatus::fromString(string $value): self` — 常量 `PENDING_PAYMENT`, `PAID`, `PREPARING`, `READY`, `COMPLETED`, `CANCELLED`
- Produces: `PaymentMethod::fromString(string $value): self` — 常量 `SELF`, `PROXY`

- [ ] **Step 1: Write failing OrderStatus test**

```php
<?php
// backend/tests/Unit/Domain/Order/ValueObjects/OrderStatusTest.php

namespace Tests\Unit\Domain\Order\ValueObjects;

use App\Domain\Order\ValueObjects\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    #[Test]
    public function paid_status_parses_correctly(): void
    {
        $status = OrderStatus::fromString('paid');
        $this->assertSame('paid', $status->value);
    }

    #[Test]
    public function invalid_status_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OrderStatus::fromString('invalid');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./scripts/docker-test.sh --filter=OrderStatusTest
```

Expected: FAIL — class not found

- [ ] **Step 3: Create migrations and value objects**

`2026_07_12_200000_create_orders_table.php`:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('order_no', 32)->unique();
    $table->foreignId('user_id')->constrained('users');
    $table->unsignedBigInteger('total_amount');
    $table->string('status', 20)->default('pending_payment');
    $table->string('payment_method', 10)->default('self');
    $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('paid_at')->nullable();
    $table->string('remark', 500)->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->string('cancel_reason', 500)->nullable();
    $table->timestamps();
});
```

`2026_07_12_200100_create_order_items_table.php`:

```php
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
    $table->foreignId('product_id')->constrained('products');
    $table->string('product_name', 200);
    $table->string('product_image', 500)->nullable();
    $table->unsignedBigInteger('price');
    $table->unsignedInteger('quantity');
    $table->unsignedBigInteger('subtotal');
    $table->timestamps();
});
```

`OrderStatus.php`（参照 `ProductStatus.php` 模式）：

```php
final class OrderStatus
{
    public const PENDING_PAYMENT = 'pending_payment';
    public const PAID = 'paid';
    public const PREPARING = 'preparing';
    public const READY = 'ready';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        $allowed = [
            self::PENDING_PAYMENT, self::PAID, self::PREPARING,
            self::READY, self::COMPLETED, self::CANCELLED,
        ];
        if (! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid status: {$value}");
        }
        return new self($value);
    }
}
```

`PaymentMethod.php` 同理：`self` / `proxy`。

- [ ] **Step 4: Run tests and migrate**

```bash
docker compose exec backend php artisan migrate
./scripts/docker-test.sh --filter=OrderStatusTest
./scripts/docker-test.sh --filter=PaymentMethodTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_07_12_200000_create_orders_table.php \
        backend/database/migrations/2026_07_12_200100_create_order_items_table.php \
        backend/app/Domain/Order/ValueObjects/ \
        backend/tests/Unit/Domain/Order/
git commit -m "feat(order): add orders schema and value objects"
```

---

### Task 2: 订单状态机（TDD）

**Files:**
- Create: `backend/app/Domain/Order/Services/OrderStateMachine.php`
- Create: `backend/app/Domain/Order/Exceptions/InvalidOrderTransitionException.php`
- Create: `backend/tests/Unit/Domain/Order/Services/OrderStateMachineTest.php`

**Interfaces:**
- Produces: `OrderStateMachine::transition(OrderStatus $from, OrderStatus $to): OrderStatus` — 合法则返回 `$to`，非法抛 `InvalidOrderTransitionException`
- Produces: `InvalidOrderTransitionException` extends `BusinessException` — code `42201`, HTTP 422

- [ ] **Step 1: Write failing state machine tests**

```php
#[Test]
public function paid_can_transition_to_preparing(): void
{
    $machine = new OrderStateMachine();
    $result = $machine->transition(
        OrderStatus::fromString('paid'),
        OrderStatus::fromString('preparing'),
    );
    $this->assertSame('preparing', $result->value);
}

#[Test]
public function paid_cannot_transition_to_cancelled(): void
{
    $machine = new OrderStateMachine();
    $this->expectException(InvalidOrderTransitionException::class);
    $machine->transition(
        OrderStatus::fromString('paid'),
        OrderStatus::fromString('cancelled'),
    );
}

#[Test]
public function pending_payment_can_transition_to_cancelled(): void
{
    $machine = new OrderStateMachine();
    $result = $machine->transition(
        OrderStatus::fromString('pending_payment'),
        OrderStatus::fromString('cancelled'),
    );
    $this->assertSame('cancelled', $result->value);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./scripts/docker-test.sh --filter=OrderStateMachineTest
```

Expected: FAIL

- [ ] **Step 3: Implement OrderStateMachine**

合法转换表：

```php
private const TRANSITIONS = [
    'pending_payment' => ['paid', 'cancelled'],
    'paid' => ['preparing'],
    'preparing' => ['ready'],
    'ready' => ['completed'],
    'completed' => [],
    'cancelled' => [],
];
```

`InvalidOrderTransitionException`:

```php
class InvalidOrderTransitionException extends BusinessException
{
    public function __construct(string $message = '非法的订单状态转换')
    {
        parent::__construct(42201, $message, 422);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./scripts/docker-test.sh --filter=OrderStateMachineTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Order/Services/ backend/app/Domain/Order/Exceptions/InvalidOrderTransitionException.php backend/tests/Unit/Domain/Order/Services/
git commit -m "feat(order): add order state machine"
```

---

### Task 3: 领域实体与仓储接口

**Files:**
- Create: `backend/app/Domain/Order/Entities/Order.php`
- Create: `backend/app/Domain/Order/Entities/OrderItem.php`
- Create: `backend/app/Domain/Order/Repositories/OrderRepositoryInterface.php`
- Create: `backend/app/Domain/Order/Exceptions/OrderNotFoundException.php`

**Interfaces:**
- Produces: `Order` entity — 字段见 design spec §4.1，含 `items: OrderItem[]`、可选 `userName`/`paidByUserName`/`userPhone`/`userDepartment`
- Produces: `OrderItem` entity — `id`, `productId`, `productName`, `productImage`, `price`, `quantity`, `subtotal`
- Produces: `OrderRepositoryInterface::findById(int $id): ?Order`
- Produces: `OrderRepositoryInterface::searchAdmin(AdminOrderListQuery $query): array{items: Order[], total: int}`
- Produces: `OrderRepositoryInterface::save(Order $order): Order`
- Produces: `OrderNotFoundException` — HTTP 404

- [ ] **Step 1: Create entities and repository interface**

`AdminOrderListQuery` 放 `Application/Order/DTO/AdminOrderListQuery.php`（本 Task 一并创建）：

```php
final class AdminOrderListQuery
{
    public function __construct(
        public readonly ?string $status,
        public readonly ?int $userId,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly string $keyword,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}
```

- [ ] **Step 2: Commit**

```bash
git add backend/app/Domain/Order/Entities/ backend/app/Domain/Order/Repositories/ backend/app/Domain/Order/Exceptions/OrderNotFoundException.php backend/app/Application/Order/DTO/
git commit -m "feat(order): add domain entities and repository interface"
```

---

### Task 4: Eloquent 仓储与模型（TDD）

**Files:**
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/OrderModel.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/OrderItemModel.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentOrderRepository.php`
- Create: `backend/database/factories/OrderFactory.php`
- Create: `backend/database/factories/OrderItemFactory.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` — bind `OrderRepositoryInterface`
- Create: `backend/tests/Feature/Infrastructure/EloquentOrderRepositoryTest.php`

**Interfaces:**
- Produces: `OrderModel::factory()` — 支持 `->paid()`, `->preparing()`, `->proxy()` 等 state
- Produces: `EloquentOrderRepository` 实现接口全部方法
- Consumes: `AdminOrderListQuery` 筛选逻辑：status、user_id、date_from/date_to（`whereDate`）、keyword（order_no LIKE 或 join users name/phone）

- [ ] **Step 1: Write failing repository integration test**

```php
#[Test]
public function search_admin_filters_by_status(): void
{
    OrderModel::factory()->create(['status' => 'paid']);
    OrderModel::factory()->create(['status' => 'cancelled']);

    $repo = app(OrderRepositoryInterface::class);
    $result = $repo->searchAdmin(new AdminOrderListQuery(
        status: 'paid', userId: null, dateFrom: null, dateTo: null,
        keyword: '', page: 1, perPage: 20,
    ));

    $this->assertCount(1, $result['items']);
    $this->assertSame('paid', $result['items'][0]->status->value);
}

#[Test]
public function find_by_id_loads_items_and_user_names(): void
{
    $user = UserModel::factory()->create(['name' => '张三']);
    $order = OrderModel::factory()->for($user, 'user')->paid()->create();
    OrderItemModel::factory()->for($order)->create(['product_name' => '拿铁']);

    $repo = app(OrderRepositoryInterface::class);
    $found = $repo->findById($order->id);

    $this->assertNotNull($found);
    $this->assertSame('张三', $found->userName);
    $this->assertCount(1, $found->items);
    $this->assertSame('拿铁', $found->items[0]->productName);
}
```

`OrderModel` 需定义 `user()` / `paidByUser()` belongsTo 关系。

- [ ] **Step 2: Run test to verify it fails**

```bash
./scripts/docker-test.sh --filter=EloquentOrderRepositoryTest
```

Expected: FAIL

- [ ] **Step 3: Implement models, factories, repository, binding**

`AppServiceProvider` 添加：

```php
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentOrderRepository;

$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
```

- [ ] **Step 4: Run tests**

```bash
./scripts/docker-test.sh --filter=EloquentOrderRepositoryTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Infrastructure/Persistence/Eloquent/Models/Order*.php backend/app/Infrastructure/Persistence/Eloquent/EloquentOrderRepository.php backend/database/factories/Order*.php backend/app/Providers/AppServiceProvider.php backend/tests/Feature/Infrastructure/EloquentOrderRepositoryTest.php
git commit -m "feat(order): add eloquent order repository"
```

---

### Task 5: Admin 列表与详情用例（TDD）

**Files:**
- Create: `backend/app/Application/Order/ListAdminOrders/ListAdminOrdersHandler.php`
- Create: `backend/app/Application/Order/GetAdminOrder/GetAdminOrderHandler.php`
- Create: `backend/tests/Unit/Application/Order/ListAdminOrdersHandlerTest.php`
- Create: `backend/tests/Unit/Application/Order/GetAdminOrderHandlerTest.php`

**Interfaces:**
- Produces: `ListAdminOrdersHandler::handle(AdminOrderListQuery $query): array{items: Order[], meta: array}`
- Produces: `GetAdminOrderHandler::handle(int $id): Order` — 不存在抛 `OrderNotFoundException`

- [ ] **Step 1: Write failing unit tests with mocked repository**

- [ ] **Step 2: Implement handlers**

- [ ] **Step 3: Run tests**

```bash
./scripts/docker-test.sh --filter=ListAdminOrdersHandlerTest
./scripts/docker-test.sh --filter=GetAdminOrderHandlerTest
```

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(order): add list and get admin order handlers"
```

---

### Task 6: 状态变更用例（TDD）

**Files:**
- Create: `backend/app/Application/Order/MarkOrderPreparing/MarkOrderPreparingHandler.php`
- Create: `backend/app/Application/Order/MarkOrderReady/MarkOrderReadyHandler.php`
- Create: `backend/app/Application/Order/CompleteOrder/CompleteOrderHandler.php`
- Create: `backend/app/Application/Order/CancelOrder/CancelOrderHandler.php`
- Create: `backend/tests/Unit/Application/Order/OrderStatusHandlersTest.php`

**Interfaces:**
- 各 Handler 注入 `OrderRepositoryInterface` + `OrderStateMachine`
- 流程：findById → 不存在抛异常 → stateMachine.transition → 更新 status（cancel 时写 cancelled_at/cancel_reason）→ save → 返回 Order
- Produces: `CancelOrderHandler::handle(int $id, ?string $cancelReason): Order`

- [ ] **Step 1: Write failing tests**

```php
#[Test]
public function cancel_sets_cancelled_at_and_reason(): void
{
    // mock repository 返回 pending_payment 订单
    // 断言 save 收到的 Order status=cancelled, cancelledAt 非空
}

#[Test]
public function mark_preparing_rejects_invalid_transition(): void
{
    $this->expectException(InvalidOrderTransitionException::class);
    // mock pending_payment 订单调用 MarkOrderPreparingHandler
}
```

- [ ] **Step 2: Implement four handlers**

- [ ] **Step 3: Run tests**

```bash
./scripts/docker-test.sh --filter=OrderStatusHandlersTest
```

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(order): add admin order status transition handlers"
```

---

### Task 7: Admin Order API（TDD）

**Files:**
- Create: `backend/app/Http/Controllers/Admin/OrderController.php`
- Create: `backend/app/Http/Requests/Admin/CancelOrderRequest.php`
- Create: `backend/app/Http/Resources/Admin/OrderResource.php`
- Create: `backend/app/Http/Resources/Admin/OrderItemResource.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Admin/OrderApiTest.php`

**Interfaces:**
- Produces: `OrderResource` — 列表/详情统一结构，嵌套 `user`/`paid_by_user`/`items`
- Routes（admin 组内）：

```php
Route::get('orders', [OrderController::class, 'index']);
Route::get('orders/{order}', [OrderController::class, 'show']);
Route::post('orders/{order}/preparing', [OrderController::class, 'preparing']);
Route::post('orders/{order}/ready', [OrderController::class, 'ready']);
Route::post('orders/{order}/complete', [OrderController::class, 'complete']);
Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
```

- [ ] **Step 1: Write failing feature tests**

```php
#[Test]
public function admin_can_list_orders_filtered_by_status(): void
{
    OrderModel::factory()->paid()->create();
    OrderModel::factory()->create(['status' => 'cancelled']);

    $this->withToken($this->adminToken())
        ->getJson('/api/v1/admin/orders?status=paid')
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
}

#[Test]
public function admin_can_mark_paid_order_preparing(): void
{
    $order = OrderModel::factory()->paid()->create();

    $this->withToken($this->adminToken())
        ->postJson("/api/v1/admin/orders/{$order->id}/preparing")
        ->assertOk()
        ->assertJsonPath('data.status', 'preparing');
}

#[Test]
public function admin_cannot_cancel_paid_order(): void
{
    $order = OrderModel::factory()->paid()->create();

    $this->withToken($this->adminToken())
        ->postJson("/api/v1/admin/orders/{$order->id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('code', 42201);
}

#[Test]
public function proxy_order_detail_includes_paid_by_user(): void
{
    $payer = UserModel::factory()->create(['name' => '李四']);
    $order = OrderModel::factory()->paid()->create([
        'payment_method' => 'proxy',
        'paid_by_user_id' => $payer->id,
    ]);

    $this->withToken($this->adminToken())
        ->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.paid_by_user.name', '李四');
}
```

复用 `ProductApiTest` 的 `adminToken()` 模式。

- [ ] **Step 2: Run tests to verify they fail**

```bash
./scripts/docker-test.sh --filter=OrderApiTest
```

Expected: FAIL

- [ ] **Step 3: Implement controller, resources, routes**

`OrderController::index` 从 query 构建 `AdminOrderListQuery`；`cancel` 用 `CancelOrderRequest` 校验 `cancel_reason` max:500。

- [ ] **Step 4: Run tests**

```bash
./scripts/docker-test.sh --filter=OrderApiTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(order): add admin order API endpoints"
```

---

### Task 8: OrderSeeder

**Files:**
- Create: `backend/database/seeders/OrderSeeder.php`

**Interfaces:**
- Produces: 至少 6 笔订单覆盖全部状态；1 笔 `payment_method=proxy` 含 `paid_by_user_id`
- 依赖：已有 `UserModel`（factory）和 `ProductModel`（factory）

- [ ] **Step 1: Create OrderSeeder**

```php
// 为每个状态创建 1 笔订单 + order_items
// proxy 订单：paid_by_user_id 指向另一用户
```

- [ ] **Step 2: Manual verify**

```bash
docker compose exec backend php artisan db:seed --class=OrderSeeder
docker compose exec backend php artisan tinker --execute="echo App\Infrastructure\Persistence\Eloquent\Models\OrderModel::count();"
```

Expected: ≥ 6

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(order): add order seeder for demo data"
```

---

### Task 9: Frontend 类型与 API 层

**Files:**
- Create: `frontend/src/types/order.ts`
- Create: `frontend/src/api/orders.ts`

**Interfaces:**
- Produces: 类型见 design spec §6.5
- Produces: `ordersApi.list/get/markPreparing/markReady/complete/cancel`

- [ ] **Step 1: Create types and API module**

`orders.ts` 参照 `products.ts` 的 `toQuery` + `request` 模式：

```typescript
export const ordersApi = {
  list(params: OrderListParams = {}): Promise<PaginatedResult<Order>> {
    return request<PaginatedResult<Order>>(`/admin/orders${toQuery(params)}`);
  },
  get(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}`);
  },
  markPreparing(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/preparing`, { method: 'POST' });
  },
  markReady(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/ready`, { method: 'POST' });
  },
  complete(id: number): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/complete`, { method: 'POST' });
  },
  cancel(id: number, cancelReason?: string): Promise<Order> {
    return request<Order>(`/admin/orders/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ cancel_reason: cancelReason ?? null }),
    });
  },
};
```

- [ ] **Step 2: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS（无 TS 错误）

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(frontend): add order types and API client"
```

---

### Task 10: OrderDetailDrawer 组件

**Files:**
- Create: `frontend/src/components/OrderDetailDrawer.tsx`

**Interfaces:**
- Consumes: `ordersApi`, `Order` type, `fenToYuan`, `resolveMediaUrl`
- Props: `open`, `orderId`, `onClose`, `onUpdated`（状态变更后刷新列表）

- [ ] **Step 1: Implement OrderDetailDrawer**

要点：
- `open && orderId` 时 `ordersApi.get(orderId)` 加载详情
- 商品图 `<Image src={resolveMediaUrl(item.product_image)} width={48} />`
- 底部按钮按 `order.status` 显隐，Popconfirm 后调对应 API
- 成功后 `message.success` + `onUpdated()` + 刷新 drawer 数据

状态 Tag 与文案映射常量放组件顶部。

- [ ] **Step 2: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(frontend): add order detail drawer"
```

---

### Task 11: OrderListPage 与路由菜单

**Files:**
- Create: `frontend/src/pages/orders/OrderListPage.tsx`
- Modify: `frontend/src/components/AdminLayout.tsx` — 菜单 + selectedKey
- Modify: `frontend/src/App.tsx` — `/orders` 路由

**Interfaces:**
- Consumes: `ordersApi`, `employeesApi`, `OrderDetailDrawer`
- Produces: 完整订单管理页面

- [ ] **Step 1: Implement OrderListPage**

参照 `ProductListPage.tsx`：
- 筛选 state → `fetchList` 依赖
- 员工 Select：`employeesApi.list({ per_page: 100 })` 加载 options
- 日期 RangePicker：`dayjs` 格式化为 `YYYY-MM-DD`
- 点击行或「详情」打开 Drawer

- [ ] **Step 2: Update AdminLayout**

```typescript
import { FileTextOutlined } from '@ant-design/icons';
// selectedKey 增加 /orders 分支
// 菜单项：订单管理 → /orders
```

- [ ] **Step 3: Update App.tsx**

```typescript
import OrderListPage from './pages/orders/OrderListPage';
// <Route path="orders" element={<OrderListPage />} />
```

- [ ] **Step 4: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(frontend): add order list page and navigation"
```

---

### Task 12: 全量验证与文档更新

**Files:**
- Modify: `docs/superpowers/specs/2026-07-12-internal-mall-design.md` — M13 状态 → ✅

- [ ] **Step 1: Run full backend tests**

```bash
./scripts/docker-test.sh
```

Expected: ALL PASS

- [ ] **Step 2: Run frontend build**

```bash
cd frontend && npm run build
```

Expected: PASS

- [ ] **Step 3: Manual acceptance checklist**

- [ ] 登录后台 → 侧边栏「订单管理」
- [ ] 状态/日期/员工/关键词筛选生效
- [ ] Drawer 展示明细、代付人、支付信息
- [ ] 备餐/可取餐/完成/取消按钮按状态显隐
- [ ] 非法操作有错误提示

- [ ] **Step 4: Update module status in internal-mall-design.md**

M13 行：`✅ 已完成 | 2026-07-12`

- [ ] **Step 5: Commit**

```bash
git commit -m "docs: mark M13 admin orders as completed"
```

---

## Spec Coverage Checklist

| Spec 要求 | Task |
|-----------|------|
| orders + order_items 迁移 | Task 1 |
| OrderStateMachine | Task 2 |
| Admin 列表筛选 | Task 4, 7 |
| Admin 详情含 items/user/paid_by | Task 4, 7 |
| 状态操作 4 端点 + cancel | Task 6, 7 |
| 非法转换 42201 | Task 2, 6, 7 |
| OrderSeeder | Task 8 |
| Frontend 列表 + Drawer | Task 10, 11 |
| 代付人展示 | Task 10 |
| 侧边栏菜单 | Task 11 |
| docker-test 全通过 | Task 12 |

## 预估

**1.5 天** — Backend 6h + Frontend 4h + 联调 2h
