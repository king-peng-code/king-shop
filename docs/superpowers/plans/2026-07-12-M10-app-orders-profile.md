# M10 App 订单管理与个人中心 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** App 端实现底部 Tab 导航、订单列表/详情/操作、个人中心，并补齐后端员工确认取餐 API。

**Architecture:** `@react-navigation/bottom-tabs` 三 Tab（商品/订单/我的）各嵌套 Native Stack；`orders.ts` 扩展 list/cancel/complete；`CompleteMyOrderHandler` 校验归属后复用 `CompleteOrderHandler`；跨 Tab 用 `navigation.getParent()?.navigate(...)` 跳转支付与订单详情。

**Tech Stack:** React Native **0.76.9** · React **18.3.1** · TypeScript **5.0.4** · React Navigation 6 · Laravel 12 · PHP 8.4

## Global Constraints

- React **18.3.1**（禁止 17 / 18.0~18.2 / 19）
- React Native **0.76.9**（禁止 < 0.76）
- API 前缀 `/api/v1/`，响应 `{ "code": 0, "message": "ok", "data": {} }`
- App **不直连数据库**，只调 Backend API
- 屏幕放 `app/src/screens/`，导航放 `app/src/navigation/`，API 放 `app/src/api/`
- 纯 StyleSheet，无 UI 库；Android 优先验收
- 后端 DDD 分层 + Feature 测试；完成门槛 `./scripts/docker-test.sh`
- App Jest：`cd app && npm run test:m10`

---

### Task 1: 后端员工确认取餐 API（TDD）

**Files:**
- Create: `backend/app/Application/Order/CompleteMyOrder/CompleteMyOrderHandler.php`
- Modify: `backend/app/Http/Controllers/Catalog/OrderController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/tests/Feature/Catalog/OrderApiTest.php`

**Interfaces:**
- Produces: `CompleteMyOrderHandler::handle(int $orderId, int $userId): Order`
- Produces: `POST /api/v1/orders/{order}/complete` → `{ code: 0, data: OrderResource }`

- [ ] **Step 1: Write failing Feature tests**

在 `backend/tests/Feature/Catalog/OrderApiTest.php` 末尾追加：

```php
#[Test]
public function employee_can_complete_ready_order(): void
{
    $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
    $order = OrderModel::factory()->for($user, 'user')->create(['status' => 'ready']);

    $this->withToken($this->employeeToken($user))
        ->postJson("/api/v1/orders/{$order->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
}

#[Test]
public function employee_cannot_complete_non_ready_order(): void
{
    $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
    $order = OrderModel::factory()->for($user, 'user')->paid()->create();

    $this->withToken($this->employeeToken($user))
        ->postJson("/api/v1/orders/{$order->id}/complete")
        ->assertStatus(422)
        ->assertJsonPath('code', 42201);
}

#[Test]
public function employee_cannot_complete_other_users_order(): void
{
    $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
    $other = UserModel::factory()->create();
    $order = OrderModel::factory()->for($other, 'user')->create(['status' => 'ready']);

    $this->withToken($this->employeeToken($user))
        ->postJson("/api/v1/orders/{$order->id}/complete")
        ->assertForbidden();
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
./scripts/docker-test.sh --filter=employee_can_complete_ready_order
```

Expected: FAIL — route/handler not found

- [ ] **Step 3: Implement CompleteMyOrderHandler**

```php
<?php
// backend/app/Application/Order/CompleteMyOrder/CompleteMyOrderHandler.php

namespace App\Application\Order\CompleteMyOrder;

use App\Application\Order\CompleteOrder\CompleteOrderHandler;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Exceptions\OrderAccessDeniedException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Repositories\OrderRepositoryInterface;

class CompleteMyOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly CompleteOrderHandler $completeOrderHandler,
    ) {}

    public function handle(int $orderId, int $userId): Order
    {
        $order = $this->repository->findById($orderId);

        if ($order === null) {
            throw new OrderNotFoundException();
        }

        if ($order->userId !== $userId) {
            throw new OrderAccessDeniedException();
        }

        return $this->completeOrderHandler->handle($orderId);
    }
}
```

- [ ] **Step 4: Register route + controller method**

`backend/routes/api.php` 在员工订单路由组追加：

```php
Route::post('/orders/{order}/complete', [CatalogOrderController::class, 'complete']);
```

`OrderController.php` 追加：

```php
use App\Application\Order\CompleteMyOrder\CompleteMyOrderHandler;

public function complete(
    Request $request,
    int $order,
    CompleteMyOrderHandler $handler,
): JsonResponse {
    return ApiResponse::success(
        new OrderResource($handler->handle($order, $request->user()->id)),
    );
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
./scripts/docker-test.sh --filter=OrderApiTest
```

Expected: all OrderApiTest PASS

- [ ] **Step 6: Commit**

```bash
git add backend/app/Application/Order/CompleteMyOrder/ \
  backend/app/Http/Controllers/Catalog/OrderController.php \
  backend/routes/api.php \
  backend/tests/Feature/Catalog/OrderApiTest.php
git commit -m "feat(M10): add employee order complete API"
```

---

### Task 2: App orderStatus 工具与 orders API 扩展（TDD）

**Files:**
- Create: `app/src/utils/orderStatus.ts`
- Create: `app/src/utils/payChannels.ts`
- Create: `app/__tests__/utils/orderStatus.test.ts`
- Modify: `app/src/api/orders.ts`
- Modify: `app/__tests__/api/orders.test.ts`
- Modify: `app/src/screens/CheckoutScreen.tsx`（改用 `payChannels.ts`）
- Modify: `app/package.json`（`test:m10` script）

**Interfaces:**
- Produces: `getOrderStatusLabel(status: OrderStatus): string`
- Produces: `getOrderStatusColor(status: OrderStatus): string`
- Produces: `selfPayChannels(): ChannelOption[]`
- Produces: `listOrders(params?: ListOrdersParams): Promise<PaginatedOrders>`
- Produces: `cancelOrder(orderId: number): Promise<Order>`
- Produces: `completeOrder(orderId: number): Promise<Order>`

- [ ] **Step 1: Write failing orderStatus test**

```typescript
// app/__tests__/utils/orderStatus.test.ts
import {getOrderStatusColor, getOrderStatusLabel} from '../../src/utils/orderStatus';

describe('orderStatus', () => {
  it('maps pending_payment label', () => {
    expect(getOrderStatusLabel('pending_payment')).toBe('待支付');
  });

  it('maps ready label', () => {
    expect(getOrderStatusLabel('ready')).toBe('可取餐');
  });

  it('returns color for paid', () => {
    expect(getOrderStatusColor('paid')).toBe('#1976d2');
  });
});
```

- [ ] **Step 2: Run — expect FAIL**

```bash
cd app && npm test -- --testPathPattern=orderStatus
```

- [ ] **Step 3: Implement orderStatus + payChannels**

```typescript
// app/src/utils/orderStatus.ts
import type {OrderStatus} from '../types/order';

const LABELS: Record<OrderStatus, string> = {
  pending_payment: '待支付',
  paid: '已支付',
  preparing: '备餐中',
  ready: '可取餐',
  completed: '已完成',
  cancelled: '已取消',
};

const COLORS: Record<OrderStatus, string> = {
  pending_payment: '#f57c00',
  paid: '#1976d2',
  preparing: '#7b1fa2',
  ready: '#2e7d32',
  completed: '#616161',
  cancelled: '#9e9e9e',
};

export function getOrderStatusLabel(status: OrderStatus): string {
  return LABELS[status];
}

export function getOrderStatusColor(status: OrderStatus): string {
  return COLORS[status];
}
```

```typescript
// app/src/utils/payChannels.ts
import type {ChannelOption} from '../components/PaymentChannelPicker';

export function selfPayChannels(): ChannelOption[] {
  const channels: ChannelOption[] = [
    {value: 'alipay_sandbox', label: '支付宝'},
    {value: 'wechat', label: '微信支付'},
  ];
  if (__DEV__) {
    channels.push({value: 'fake', label: '模拟支付（开发）'});
  }
  return channels;
}
```

- [ ] **Step 4: Extend orders API + tests**

`app/src/api/orders.ts` 追加类型与函数：

```typescript
export interface ListOrdersParams {
  status?: OrderStatus;
  page?: number;
  per_page?: number;
}

export interface PaginatedOrders {
  items: Order[];
  meta: {total: number; page: number; per_page: number};
}

export async function listOrders(
  params: ListOrdersParams = {},
): Promise<PaginatedOrders> {
  const search = new URLSearchParams();
  if (params.status) search.set('status', params.status);
  if (params.page) search.set('page', String(params.page));
  if (params.per_page) search.set('per_page', String(params.per_page));
  const qs = search.toString();
  return apiRequest<PaginatedOrders>(`/orders${qs ? `?${qs}` : ''}`);
}

export async function cancelOrder(orderId: number): Promise<Order> {
  return apiRequest<Order>(`/orders/${orderId}/cancel`, {method: 'POST'});
}

export async function completeOrder(orderId: number): Promise<Order> {
  return apiRequest<Order>(`/orders/${orderId}/complete`, {method: 'POST'});
}
```

`app/__tests__/api/orders.test.ts` 追加：

```typescript
import {cancelOrder, completeOrder, listOrders} from '../../src/api/orders';

it('listOrders passes status query', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {items: [sampleOrder], meta: {total: 1, page: 1, per_page: 20}},
    }),
  });

  const result = await listOrders({status: 'pending_payment'});
  expect(result.items).toHaveLength(1);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/status=pending_payment/),
    expect.any(Object),
  );
});

it('cancelOrder posts to cancel endpoint', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {...sampleOrder, status: 'cancelled'},
    }),
  });

  const result = await cancelOrder(10);
  expect(result.status).toBe('cancelled');
});

it('completeOrder posts to complete endpoint', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {...sampleOrder, status: 'completed'},
    }),
  });

  const result = await completeOrder(10);
  expect(result.status).toBe('completed');
});
```

- [ ] **Step 5: Refactor CheckoutScreen to use payChannels**

删除 `CheckoutScreen.tsx` 内本地 `selfPayChannels`，改为：

```typescript
import {selfPayChannels} from '../utils/payChannels';
```

- [ ] **Step 6: Add test:m10 script**

`app/package.json`:

```json
"test:m10": "jest --testPathPattern='orderStatus|orders'"
```

- [ ] **Step 7: Run tests — expect PASS**

```bash
cd app && npm run test:m10
```

- [ ] **Step 8: Commit**

```bash
git add app/src/utils/orderStatus.ts app/src/utils/payChannels.ts \
  app/src/api/orders.ts app/__tests__/utils/orderStatus.test.ts \
  app/__tests__/api/orders.test.ts app/src/screens/CheckoutScreen.tsx app/package.json
git commit -m "feat(M10): add order status utils and orders API extensions"
```

---

### Task 3: Bottom Tab 导航重构

**Files:**
- Modify: `app/package.json`（`@react-navigation/bottom-tabs`）
- Create: `app/src/navigation/MainTabNavigator.tsx`
- Modify: `app/src/navigation/types.ts`
- Modify: `app/src/navigation/RootNavigator.tsx`
- Modify: 所有引用 `MainStackParamList` 的 screen（改为 `ShopStackParamList`）

**Interfaces:**
- Produces: `MainTabParamList = { ShopTab; OrdersTab; ProfileTab }`
- Produces: `ShopStackParamList`（原 MainStackParamList 内容）
- Produces: `OrdersStackParamList = { OrdersList; OrderDetail: { orderId } }`
- Produces: `ProfileStackParamList = { Profile; ChangePassword }`

- [ ] **Step 1: Install bottom-tabs**

```bash
cd app && npm install @react-navigation/bottom-tabs
```

- [ ] **Step 2: Rewrite types.ts**

```typescript
// app/src/navigation/types.ts
import type {NavigatorScreenParams} from '@react-navigation/native';
import type {PayChannel, PaymentOutcome} from '../types/order';

export type ShopStackParamList = {
  Home: undefined;
  ProductDetail: {productId: number};
  Checkout: {productId: number; quantity: number};
  Payment: {orderId: number; channel: PayChannel};
  ProxyShare: {orderId: number};
  PaymentResult: {orderId: number; outcome: PaymentOutcome};
};

export type OrdersStackParamList = {
  OrdersList: undefined;
  OrderDetail: {orderId: number};
};

export type ProfileStackParamList = {
  Profile: undefined;
  ChangePassword: undefined;
};

export type MainTabParamList = {
  ShopTab: NavigatorScreenParams<ShopStackParamList>;
  OrdersTab: NavigatorScreenParams<OrdersStackParamList>;
  ProfileTab: NavigatorScreenParams<ProfileStackParamList>;
};

export type AuthStackParamList = {Login: undefined};
export type ChangePasswordStackParamList = {ChangePassword: undefined};

/** @deprecated use ShopStackParamList */
export type MainStackParamList = ShopStackParamList;
```

- [ ] **Step 3: Create MainTabNavigator.tsx**

```typescript
// app/src/navigation/MainTabNavigator.tsx
import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import HomeScreen from '../screens/HomeScreen';
import ProductDetailScreen from '../screens/ProductDetailScreen';
import CheckoutScreen from '../screens/CheckoutScreen';
import PaymentScreen from '../screens/PaymentScreen';
import ProxyShareScreen from '../screens/ProxyShareScreen';
import PaymentResultScreen from '../screens/PaymentResultScreen';
import OrdersScreen from '../screens/OrdersScreen';
import OrderDetailScreen from '../screens/OrderDetailScreen';
import ProfileScreen from '../screens/ProfileScreen';
import ChangePasswordScreen from '../screens/ChangePasswordScreen';
import type {
  MainTabParamList,
  OrdersStackParamList,
  ProfileStackParamList,
  ShopStackParamList,
} from './types';

const Tab = createBottomTabNavigator<MainTabParamList>();
const ShopStack = createNativeStackNavigator<ShopStackParamList>();
const OrdersStack = createNativeStackNavigator<OrdersStackParamList>();
const ProfileStack = createNativeStackNavigator<ProfileStackParamList>();

function ShopNavigator() {
  return (
    <ShopStack.Navigator>
      <ShopStack.Screen name="Home" component={HomeScreen} options={{title: '商品'}} />
      <ShopStack.Screen name="ProductDetail" component={ProductDetailScreen} options={{title: '商品详情'}} />
      <ShopStack.Screen name="Checkout" component={CheckoutScreen} options={{title: '确认订单'}} />
      <ShopStack.Screen name="Payment" component={PaymentScreen} options={{title: '支付', headerBackVisible: false}} />
      <ShopStack.Screen name="ProxyShare" component={ProxyShareScreen} options={{title: '找人代付', headerBackVisible: false}} />
      <ShopStack.Screen name="PaymentResult" component={PaymentResultScreen} options={{title: '支付结果', headerBackVisible: false}} />
    </ShopStack.Navigator>
  );
}

function OrdersNavigator() {
  return (
    <OrdersStack.Navigator>
      <OrdersStack.Screen name="OrdersList" component={OrdersScreen} options={{title: '订单'}} />
      <OrdersStack.Screen name="OrderDetail" component={OrderDetailScreen} options={{title: '订单详情'}} />
    </OrdersStack.Navigator>
  );
}

function ProfileNavigator() {
  return (
    <ProfileStack.Navigator>
      <ProfileStack.Screen name="Profile" component={ProfileScreen} options={{title: '我的'}} />
      <ProfileStack.Screen name="ChangePassword" component={ChangePasswordScreen} options={{title: '修改密码'}} />
    </ProfileStack.Navigator>
  );
}

export default function MainTabNavigator() {
  return (
    <Tab.Navigator screenOptions={{headerShown: false}}>
      <Tab.Screen name="ShopTab" component={ShopNavigator} options={{title: '商品'}} />
      <Tab.Screen name="OrdersTab" component={OrdersNavigator} options={{title: '订单'}} />
      <Tab.Screen name="ProfileTab" component={ProfileNavigator} options={{title: '我的'}} />
    </Tab.Navigator>
  );
}
```

- [ ] **Step 4: Update RootNavigator**

将 `MainNavigator` 替换为：

```typescript
import MainTabNavigator from './MainTabNavigator';
// ...
return <MainTabNavigator />;
```

删除原 `MainStack` 内联定义。

- [ ] **Step 5: Create placeholder screens（最小可编译）**

先创建三个占位 Screen，Task 4–6 再填充：

```typescript
// OrdersScreen.tsx / OrderDetailScreen.tsx / ProfileScreen.tsx
export default function XxxScreen() {
  return null;
}
```

- [ ] **Step 6: Update Shop stack screen imports**

将所有 `MainStackParamList` 引用改为 `ShopStackParamList`（HomeScreen、ProductDetailScreen、CheckoutScreen、PaymentScreen、ProxyShareScreen、PaymentResultScreen）。

- [ ] **Step 7: Verify app compiles**

```bash
cd app && npx tsc --noEmit
```

- [ ] **Step 8: Commit**

```bash
git add app/package.json app/package-lock.json app/src/navigation/ \
  app/src/screens/OrdersScreen.tsx app/src/screens/OrderDetailScreen.tsx \
  app/src/screens/ProfileScreen.tsx
git commit -m "feat(M10): add bottom tab navigation with three stacks"
```

---

### Task 4: 订单列表（OrderStatusTabs + OrderListItem + OrdersScreen）

**Files:**
- Create: `app/src/components/OrderStatusTabs.tsx`
- Create: `app/src/components/OrderListItem.tsx`
- Modify: `app/src/screens/OrdersScreen.tsx`

**Interfaces:**
- Consumes: `listOrders`, `getOrderStatusLabel`, `getOrderStatusColor`, `formatPrice`
- Produces: `OrderStatusTabKey = 'all' | 'pending_payment' | 'in_progress' | 'ready' | 'completed'`
- Produces: `fetchOrdersForTab(tab, page): Promise<PaginatedOrders>`（OrdersScreen 内 helper，进行中 Tab 双请求合并）

- [ ] **Step 1: Implement OrderStatusTabs**

横向 `ScrollView`，5 个 Tab：全部 / 待支付 / 进行中 / 可取餐 / 已完成。选中态蓝色下划线 + 加粗。

- [ ] **Step 2: Implement OrderListItem**

Pressable 卡片：订单号、首件 `items[0].product_name`（无 items 显示「订单商品」）、`formatPrice(total_amount)`、状态标签（彩色圆角）、`created_at` 格式化 `MM-DD HH:mm`。

- [ ] **Step 3: Implement OrdersScreen**

核心逻辑：

```typescript
type TabKey = 'all' | 'pending_payment' | 'in_progress' | 'ready' | 'completed';

async function loadTab(tab: TabKey, page: number): Promise<PaginatedOrders> {
  if (tab === 'in_progress') {
    const [paid, preparing] = await Promise.all([
      listOrders({status: 'paid', page, per_page: 20}),
      listOrders({status: 'preparing', page, per_page: 20}),
    ]);
    const items = [...paid.items, ...preparing.items].sort(
      (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    );
    return {
      items,
      meta: {
        total: paid.meta.total + preparing.meta.total,
        page,
        per_page: 20,
      },
    };
  }
  const status = tab === 'all' ? undefined : tab;
  return listOrders({status, page, per_page: 20});
}
```

- FlatList + RefreshControl + onEndReached 分页
- `useFocusEffect` 进入页面时刷新
- 点击 item → `navigation.navigate('OrderDetail', { orderId: item.id })`
- 空列表 → `<EmptyState message="暂无订单" />`

- [ ] **Step 4: Manual smoke on Android emulator**

登录 → 订单 Tab → 切换状态 Tab → 列表展示

- [ ] **Step 5: Commit**

```bash
git add app/src/components/OrderStatusTabs.tsx app/src/components/OrderListItem.tsx \
  app/src/screens/OrdersScreen.tsx
git commit -m "feat(M10): add orders list with status tabs"
```

---

### Task 5: 订单详情（OrderDetailScreen）

**Files:**
- Modify: `app/src/screens/OrderDetailScreen.tsx`

**Interfaces:**
- Consumes: `getOrder`, `cancelOrder`, `completeOrder`, `selfPayChannels`, `PaymentChannelPicker`
- Consumes: 跨 Tab 导航 `navigation.getParent()?.navigate('ShopTab', { screen: 'Payment', params: { orderId, channel } })`

- [ ] **Step 1: Load order on mount**

`useRoute<RouteProp<OrdersStackParamList, 'OrderDetail'>>()` 取 `orderId`，`getOrder` 加载，LoadingView / 错误态。

- [ ] **Step 2: Render detail sections**

- 顶部状态徽章（`getOrderStatusLabel` + `getOrderStatusColor` 背景）
- 商品列表：图（有则 Image）、名、单价 × 数量、小计
- 合计行、备注（有则显示）
- 支付方式：`self` → 自己付，`proxy` → 找人代付
- 代付人：`paid_by_user?.name`
- 时间：下单时间、支付时间（有则）

- [ ] **Step 3: Action buttons**

`pending_payment`：
- 「去支付」→ 底部 Modal 展示 `PaymentChannelPicker` + 确认 → 跨 Tab navigate Payment
- 「取消订单」→ `Alert.alert('确认取消', ...)` → `cancelOrder` → 刷新

`ready`：
- 「确认取餐」→ `Alert.alert('确认取餐', '确认已取到餐品？')` → `completeOrder` → 刷新

- [ ] **Step 4: useFocusEffect refresh**

从 Payment 返回后自动刷新订单状态。

- [ ] **Step 5: Commit**

```bash
git add app/src/screens/OrderDetailScreen.tsx
git commit -m "feat(M10): add order detail with cancel, pay, and complete actions"
```

---

### Task 6: 个人中心（ProfileScreen）

**Files:**
- Modify: `app/src/screens/ProfileScreen.tsx`

**Interfaces:**
- Consumes: `useAuth()` → `user`, `logout`
- Produces: navigate `ChangePassword`；logout → Alert 确认

- [ ] **Step 1: Render user info card**

```typescript
const {user, logout} = useAuth();
// 姓名首字圆形头像
// 行：姓名 / 部门（无则「未设置」）/ 手机号 / 工号（有则）
```

- [ ] **Step 2: Action buttons**

- 「修改密码」→ `navigation.navigate('ChangePassword')`
- 「退出登录」→ Alert → `logout()`

- [ ] **Step 3: Verify ProfileTab ChangePassword**

从个人中心进入改密，成功后 `refreshUser` 停留 Profile（ChangePasswordScreen 已有 `refreshUser` 逻辑，确认改密后 `navigation.goBack()`）。

- [ ] **Step 4: Commit**

```bash
git add app/src/screens/ProfileScreen.tsx
git commit -m "feat(M10): add profile screen with change password and logout"
```

---

### Task 7: PaymentResult 衔接 + 验收脚本

**Files:**
- Modify: `app/src/screens/PaymentResultScreen.tsx`
- Create: `scripts/test-m10-acceptance.sh`
- Modify: `docs/superpowers/specs/2026-07-12-internal-mall-design.md`（M10 状态 → 进行中/已完成）

**Interfaces:**
- Consumes: `navigation.getParent()?.navigate('OrdersTab', { screen: 'OrderDetail', params: { orderId } })`

- [ ] **Step 1: Update PaymentResultScreen**

`outcome === 'success' || outcome === 'pending'` 时展示两个按钮：

```typescript
<Pressable onPress={() =>
  navigation.getParent()?.navigate('OrdersTab', {
    screen: 'OrderDetail',
    params: {orderId},
  })
}>
  <Text>查看订单</Text>
</Pressable>
<Pressable onPress={() => navigation.popToTop()}>
  <Text>返回首页</Text>
</Pressable>
```

失败态保持仅「返回首页」。

- [ ] **Step 2: Create acceptance script**

```bash
#!/usr/bin/env bash
# scripts/test-m10-acceptance.sh
set -euo pipefail
cd "$(dirname "$0")/.."
./scripts/docker-test.sh --filter=OrderApiTest
cd app && npm run test:m10
echo "M10 acceptance tests passed"
```

```bash
chmod +x scripts/test-m10-acceptance.sh
```

- [ ] **Step 3: Run full acceptance**

```bash
./scripts/test-m10-acceptance.sh
```

Expected: backend OrderApiTest PASS + app test:m10 PASS

- [ ] **Step 4: Commit**

```bash
git add app/src/screens/PaymentResultScreen.tsx scripts/test-m10-acceptance.sh
git commit -m "feat(M10): link payment result to order detail and add acceptance script"
```

---

### Task 8: 文档记录

**Files:**
- Create: `docs/superpowers/records/2026-07-12-M10-app-orders-profile.md`
- Modify: `docs/superpowers/specs/2026-07-12-internal-mall-design.md`（M10 行状态）

- [ ] **Step 1: Write execution record**

记录：完成任务列表、验收命令、手工检查项。

- [ ] **Step 2: Update module tracker**

`internal-mall-design.md` 模块表 M10 → `✅ 已完成`

- [ ] **Step 3: Commit**

```bash
git add docs/superpowers/records/2026-07-12-M10-app-orders-profile.md \
  docs/superpowers/specs/2026-07-12-internal-mall-design.md \
  docs/superpowers/specs/2026-07-12-M10-app-orders-profile-design.md
git commit -m "docs(M10): add design spec and execution record"
```

---

## 手工验收清单

- [ ] 登录后底部显示 商品 / 订单 / 我的 三个 Tab
- [ ] 订单 Tab：5 个状态筛选正确；下拉刷新有效
- [ ] 待支付订单：可取消；可去支付并完成 fake 支付
- [ ] 可取餐订单（后台 mark ready）：可确认取餐，状态变已完成
- [ ] 个人中心：姓名、部门、手机号正确；改密、退出可用
- [ ] 支付成功页「查看订单」跳转订单详情

## 执行选项

计划已保存至 `docs/superpowers/plans/2026-07-12-M10-app-orders-profile.md`。

**1. Subagent-Driven（推荐）** — 每 Task 派发子 agent，逐 Task 审查  
**2. Inline Execution** — 本会话按 Task 顺序直接实施

请选择执行方式。
