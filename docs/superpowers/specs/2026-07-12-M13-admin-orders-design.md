# M13 — 管理后台：订单管理 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **依赖：** M03 用户认证（已完成）、M04 商品目录（已完成）、M11 管理后台基础（已完成）、M12 后台商品 UI（已完成）  
> **后续依赖方：** M15 数据统计

---

## 1. 目标

实现 **M13 管理后台订单管理** 与 **M05 管理端 API 子集**：订单列表筛选、Drawer 详情、状态操作（备餐/可取餐/完成/取消），对接 Backend Admin Order API。

**交付物：**

**Backend（M05 子集）：**
- Migrations: `orders`, `order_items`
- DDD 分层：实体、状态机、仓储、Admin 用例
- Admin API：列表、详情、状态变更（独立 POST 端点）
- `OrderSeeder`（演示数据）
- Unit + Feature + Integration 测试

**Frontend（M13）：**
- `frontend/src/pages/orders/OrderListPage.tsx`
- `frontend/src/components/OrderDetailDrawer.tsx`
- `frontend/src/api/orders.ts`、`frontend/src/types/order.ts`
- `AdminLayout` 侧边栏新增「订单管理」→ `/orders`

**非目标（本期不做）：**
- App 下单 `POST /api/v1/orders`、库存扣减/回滚
- 支付对接、`payments` 表（M06）
- 代付链接生成（M07）；仅展示已有 `paid_by_user` 字段
- 超时取消 Job `CancelExpiredOrders`
- 退款 `refunded` 状态
- 已支付订单管理员取消（仅 `pending_payment` 可取消）
- 前端自动化测试（Vitest / Playwright）

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| 范围 | **方案 A**：M13 前端 + M05 Admin API 子集 | M05 后端尚未实现，合并交付可验收全链路 |
| 状态变更 API | **独立 POST 端点**（`/preparing`、`/ready`、`/complete`、`/cancel`） | 语义清晰，前端按钮一一对应，权限扩展方便 |
| 详情交互 | **列表 + 右侧 Drawer** | 保持列表上下文，适合订单明细 + 操作按钮 |
| 管理员取消 | **仅 `pending_payment`** | 符合状态机，已支付走备餐流程 |
| 金额 | 整数分存储，前端 `fenToYuan` 展示 | 与 M04 商品一致 |
| 测试 | 后端 TDD + 前端手工验收 | 与 M11/M14 一致 |

---

## 3. 订单状态机

```
pending_payment → paid → preparing → ready → completed
       ↓
   cancelled（仅 pending_payment）
```

| 状态 | 含义 | 管理员可操作 |
|---|---|---|
| `pending_payment` | 待支付 | 取消 |
| `paid` | 已支付 | 开始备餐 |
| `preparing` | 备餐中 | 标记可取餐 |
| `ready` | 可取餐 | 完成订单 |
| `completed` | 已完成 | — |
| `cancelled` | 已取消 | — |

非法转换抛 `InvalidOrderTransitionException`（HTTP 422，code `42201`）。

---

## 4. 数据模型

### 4.1 `orders`

| 列 | 类型 | 说明 |
|---|---|---|
| `id` | bigint PK | |
| `order_no` | varchar(32) unique | 如 `KS202607121430001` |
| `user_id` | FK users | 下单员工 |
| `total_amount` | unsignedBigInteger | 总金额（分） |
| `status` | varchar(20) | 见状态机 |
| `payment_method` | varchar(10) | `self` / `proxy` |
| `paid_by_user_id` | FK users nullable | 代付付款人 |
| `paid_at` | timestamp nullable | |
| `remark` | varchar(500) nullable | 备注 |
| `cancelled_at` | timestamp nullable | |
| `cancel_reason` | varchar(500) nullable | |
| `created_at` / `updated_at` | timestamps | |

### 4.2 `order_items`

| 列 | 类型 | 说明 |
|---|---|---|
| `id` | bigint PK | |
| `order_id` | FK orders | |
| `product_id` | FK products | 关联商品（快照不依赖商品仍存在） |
| `product_name` | varchar(200) | 下单时快照 |
| `product_image` | varchar(500) nullable | 下单时快照路径 |
| `price` | unsignedBigInteger | 单价（分） |
| `quantity` | unsignedInteger | |
| `subtotal` | unsignedBigInteger | 小计（分） |
| `created_at` / `updated_at` | timestamps | |

---

## 5. Backend 架构

### 5.1 目录结构

```
backend/app/
├── Domain/Order/
│   ├── Entities/Order.php, OrderItem.php
│   ├── ValueObjects/OrderStatus.php, PaymentMethod.php
│   ├── Services/OrderStateMachine.php
│   ├── Repositories/OrderRepositoryInterface.php
│   └── Exceptions/OrderNotFoundException.php, InvalidOrderTransitionException.php
├── Application/Order/
│   ├── DTO/AdminOrderListQuery.php
│   ├── ListAdminOrders/ListAdminOrdersHandler.php
│   ├── GetAdminOrder/GetAdminOrderHandler.php
│   ├── MarkOrderPreparing/MarkOrderPreparingHandler.php
│   ├── MarkOrderReady/MarkOrderReadyHandler.php
│   ├── CompleteOrder/CompleteOrderHandler.php
│   └── CancelOrder/CancelOrderHandler.php
├── Infrastructure/Persistence/Eloquent/
│   ├── Models/OrderModel.php, OrderItemModel.php
│   └── EloquentOrderRepository.php
└── Http/
    ├── Controllers/Admin/OrderController.php
    ├── Requests/Admin/CancelOrderRequest.php
    └── Resources/Admin/OrderResource.php, OrderItemResource.php
```

### 5.2 Admin API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/admin/orders` | 分页列表 |
| GET | `/api/v1/admin/orders/{id}` | 详情（含 items、user、paid_by_user） |
| POST | `/api/v1/admin/orders/{id}/preparing` | paid → preparing |
| POST | `/api/v1/admin/orders/{id}/ready` | preparing → ready |
| POST | `/api/v1/admin/orders/{id}/complete` | ready → completed |
| POST | `/api/v1/admin/orders/{id}/cancel` | pending_payment → cancelled |

**列表查询参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `status` | string | 订单状态 |
| `user_id` | int | 下单员工 ID |
| `date_from` | date `Y-m-d` | 创建日起 |
| `date_to` | date `Y-m-d` | 创建日止（含当天） |
| `keyword` | string | 订单号 / 员工姓名 / 手机号 |
| `page` | int | 默认 1 |
| `per_page` | int | 默认 20，最大 100 |

**列表响应：**

```json
{
  "code": 0,
  "data": {
    "items": [
      {
        "id": 1,
        "order_no": "KS202607121430001",
        "user": { "id": 2, "name": "张三", "phone": "13800000001" },
        "total_amount": 3000,
        "status": "paid",
        "payment_method": "self",
        "paid_by_user": null,
        "paid_at": "2026-07-12T14:30:00+08:00",
        "created_at": "2026-07-12T14:30:00+08:00"
      }
    ],
    "meta": { "total": 10, "page": 1, "per_page": 20 }
  }
}
```

**详情额外字段：** `remark`、`cancelled_at`、`cancel_reason`、`items[]`

**取消请求体（可选）：**

```json
{ "cancel_reason": "员工要求取消" }
```

### 5.3 OrderResource 字段

与前端 `Order` / `OrderItem` 类型对齐；`product_image` 返回存储路径，前端用 `resolveMediaUrl()` 拼接。

---

## 6. Frontend 架构

### 6.1 路由

| 路径 | 组件 | 鉴权 |
|------|------|------|
| `/orders` | OrderListPage | 需 token + 已改密 + admin |

### 6.2 目录结构

```
frontend/src/
├── api/orders.ts
├── types/order.ts
├── components/OrderDetailDrawer.tsx
├── pages/orders/OrderListPage.tsx
```

### 6.3 OrderListPage

**筛选栏：**
- 状态 `Select`（全部 + 6 种状态）
- 日期 `DatePicker.RangePicker` → `date_from` / `date_to`
- 员工 `Select`（`employeesApi.list` 加载，可搜索）
- 关键词 `Input.Search`（订单号，防抖 300ms）

**表格列：**

| 列 | 渲染 |
|----|------|
| 订单号 | `order_no` |
| 员工 | `user.name` |
| 金额 | `fenToYuan(total_amount)` 前缀 `¥` |
| 状态 | 彩色 Tag |
| 支付方式 | self=自付 / proxy=代付 |
| 代付人 | `paid_by_user?.name`，非代付显示 `-` |
| 下单时间 | `created_at` 格式化 |
| 操作 | 「详情」按钮 |

**分页：** 绑定 `meta.page` / `meta.per_page` / `meta.total`

### 6.4 OrderDetailDrawer

**区块：**
1. 基本信息：订单号、状态 Tag、下单时间、备注
2. 员工信息：姓名、手机号、部门
3. 支付信息：方式、支付时间；代付时展示付款人
4. 商品明细 Table：缩略图、名称、单价、数量、小计
5. 操作按钮（底部，按状态显隐，Popconfirm 确认）

**状态按钮显隐：**

| 状态 | 按钮 |
|------|------|
| `pending_payment` | 取消订单 |
| `paid` | 开始备餐 |
| `preparing` | 标记可取餐 |
| `ready` | 完成订单 |
| `completed` / `cancelled` | 无 |

**状态 Tag 配色：**

| 状态 | 颜色 |
|------|------|
| `pending_payment` | orange |
| `paid` | blue |
| `preparing` | purple |
| `ready` | green |
| `completed` | default |
| `cancelled` | red |

### 6.5 TypeScript 类型

```typescript
type OrderStatus =
  | 'pending_payment'
  | 'paid'
  | 'preparing'
  | 'ready'
  | 'completed'
  | 'cancelled';

type PaymentMethod = 'self' | 'proxy';

interface OrderUser {
  id: number;
  name: string;
  phone: string;
  department?: string | null;
}

interface OrderItem {
  id: number;
  product_id: number;
  product_name: string;
  product_image: string | null;
  price: number;
  quantity: number;
  subtotal: number;
}

interface Order {
  id: number;
  order_no: string;
  user: OrderUser;
  total_amount: number;
  status: OrderStatus;
  payment_method: PaymentMethod;
  paid_by_user: OrderUser | null;
  paid_at: string | null;
  remark: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
  created_at: string;
  items?: OrderItem[];
}

interface OrderListParams {
  status?: OrderStatus;
  user_id?: number;
  date_from?: string;
  date_to?: string;
  keyword?: string;
  page?: number;
  per_page?: number;
}
```

### 6.6 API 方法

```typescript
ordersApi.list(params)           // GET /admin/orders
ordersApi.get(id)                // GET /admin/orders/{id}
ordersApi.markPreparing(id)      // POST .../preparing
ordersApi.markReady(id)          // POST .../ready
ordersApi.complete(id)           // POST .../complete
ordersApi.cancel(id, reason?)   // POST .../cancel
```

---

## 7. 错误处理

| 场景 | HTTP / code | UI 行为 |
|------|-------------|---------|
| 订单不存在 | 404 | `message.error('订单不存在')` |
| 非法状态转换 | 422 / 42201 | `message.error(后端 message)` |
| 校验失败 | 422 | 字段错误 |
| 无权操作 | 403 | `message.error('无权操作')` |
| 网络异常 | — | `message.error('网络异常，请重试')` |

---

## 8. 测试策略

| 类型 | 覆盖 |
|------|------|
| Unit | `OrderStatus`、`PaymentMethod`、`OrderStateMachine` |
| Unit | 各 Handler（Mock 仓储） |
| Feature | `Admin/OrderApiTest`：列表筛选、详情、各状态操作、非法转换 |
| Integration | `EloquentOrderRepositoryTest` |
| Frontend | 手工验收清单 |

**完成门槛：** `./scripts/docker-test.sh` 全通过 + `npm run build` 无 TS 错误

---

## 9. 演示数据

`OrderSeeder` 创建覆盖各状态的订单（含 1 笔代付订单），需先有用户与商品：

```bash
docker compose exec backend php artisan db:seed --class=OrderSeeder
```

不默认加入 `DatabaseSeeder`，避免污染测试库。

---

## 10. 验收标准

- [ ] 侧边栏「订单管理」可进入 `/orders`
- [ ] 可按状态 / 日期范围 / 员工 / 关键词筛选
- [ ] Drawer 展示商品明细、支付信息、代付人
- [ ] 状态按钮按状态机显隐
- [ ] 非法状态操作后端拒绝并提示
- [ ] `./scripts/docker-test.sh` 全部通过
- [ ] `npm run build` 无 TypeScript 错误

---

## 11. 预估

**1.5 天**

| 任务 | 预估 |
|------|------|
| Backend 迁移 + 领域层 + 状态机 | 3h |
| Backend Admin API + 测试 | 3h |
| Frontend 列表 + Drawer + API | 3h |
| OrderSeeder + 联调验收 | 1h |
