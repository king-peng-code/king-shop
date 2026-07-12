# M15 — 管理后台：数据统计 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **依赖：** M05 订单领域（已完成）、M11 管理后台基础（已完成）、M13 后台订单 UI（已完成）  
> **后续依赖方：** M16 部署

---

## 1. 目标

实现管理后台 **数据统计仪表盘**：今日/本周关键指标、订单状态分布、热门商品双榜、本周每日销售趋势；对接 `GET /api/v1/admin/dashboard/stats`。

**交付物：**

**Backend：**
- `GET /api/v1/admin/dashboard/stats`
- DDD 分层：Dashboard 读模型仓储、Application 用例、Admin Controller
- Unit + Integration + Feature 测试

**Frontend：**
- `frontend/src/pages/dashboard/DashboardPage.tsx`
- `frontend/src/api/dashboard.ts`、`frontend/src/types/dashboard.ts`
- `recharts` 图表（柱状图 + 饼图）
- `AdminLayout` 侧边栏首位「数据统计」→ `/dashboard`；`/` 默认重定向 `/dashboard`

**非目标（M15 不做）：**
- 日期范围筛选器、导出报表
- Redis 缓存统计结果
- 实时推送 / WebSocket 刷新
- 前端自动化测试（Vitest / Playwright）
- 员工维度分析、支付渠道分析

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| API 形态 | **方案 A**：单一聚合端点 | 仪表盘一次加载，符合总体 spec，前端简单 |
| 默认首页 | **`/dashboard`** | 数据统计作为管理后台概览入口 |
| 销售额口径 | **已支付即计入** | `status IN (paid, preparing, ready, completed)`，按 `paid_at` 归属 |
| 本周范围 | **自然周** | 周一 00:00 至当前，`Asia/Shanghai` |
| 热门商品 | **销量榜 + 销售额榜各 Top 5** | 双维度展示，数据来自本周已支付订单 |
| 状态分布 | **全部订单** | 按 `status` 分组，展示当前订单管道全貌 |
| 图表库 | **recharts** | 总体 spec 推荐，轻量且满足饼图/柱状图需求 |
| 测试 | 后端 TDD + 前端手工验收 | 与 M11/M13/M14 一致 |

---

## 3. 页面布局

```
┌─────────────────────────────────────────────────────────────┐
│  今日订单 │ 今日销售额 │ 本周订单 │ 本周销售额               │  Statistic 卡片
├──────────────────────────┬──────────────────────────────────┤
│  本周每日销售额（柱状图）    │  订单状态分布（饼图）              │  recharts
├──────────────────────────┴──────────────────────────────────┤
│  热门商品·销量 Top 5        │  热门商品·销售额 Top 5            │  Table
└─────────────────────────────────────────────────────────────┘
```

### 路由变更

| 路径 | 组件 | 说明 |
|------|------|------|
| `/dashboard` | `DashboardPage` | 新增，登录后默认页 |
| `/` | redirect → `/dashboard` | 原 `/employees` 保留不变 |

### 目录结构

```
frontend/src/
├── api/
│   └── dashboard.ts
├── pages/
│   └── dashboard/
│       └── DashboardPage.tsx
└── types/
    └── dashboard.ts
```

---

## 4. 统计口径

时区统一 **`Asia/Shanghai`**（与 Laravel `config/app.php` timezone 一致）。

### 4.1 已支付订单定义

```sql
status IN ('paid', 'preparing', 'ready', 'completed')
AND paid_at IS NOT NULL
```

### 4.2 时间边界

| 范围 | 起止 |
|------|------|
| 今日 | 当天 00:00:00 ~ 当前时刻 |
| 本周 | 本周一 00:00:00 ~ 当前时刻 |

### 4.3 各指标

| 字段 | 计算 |
|------|------|
| `today.order_count` | `orders.created_at` 在今日范围内，全部状态 |
| `today.paid_order_count` | 已支付订单且 `paid_at` 在今日 |
| `today.sales_amount` | 同上，`SUM(total_amount)`（分） |
| `week.*` | 同上，范围换为本周 |
| `status_distribution` | 全部订单 `GROUP BY status` |
| `hot_products_by_quantity` | 本周已支付订单的 `order_items`，`GROUP BY product_id`，按 `SUM(quantity)` DESC，LIMIT 5 |
| `hot_products_by_sales` | 同上，按 `SUM(subtotal)` DESC，LIMIT 5 |
| `week_daily_sales` | 本周已支付订单，按 `DATE(paid_at)` 分组，返回周一~周日每天 `sales_amount` + `order_count`（无数据日期填 0） |

---

## 5. Backend API

### 5.1 端点

```
GET /api/v1/admin/dashboard/stats
```

**鉴权：** `auth:sanctum` + `password.changed` + `admin`

**响应示例：**

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "summary": {
      "today": {
        "order_count": 3,
        "paid_order_count": 2,
        "sales_amount": 6000
      },
      "week": {
        "order_count": 12,
        "paid_order_count": 9,
        "sales_amount": 27000
      }
    },
    "status_distribution": [
      { "status": "paid", "label": "已支付", "count": 5 },
      { "status": "completed", "label": "已完成", "count": 3 }
    ],
    "hot_products_by_quantity": [
      {
        "product_id": 1,
        "product_name": "拿铁",
        "quantity": 20,
        "sales_amount": 30000
      }
    ],
    "hot_products_by_sales": [
      {
        "product_id": 1,
        "product_name": "拿铁",
        "quantity": 20,
        "sales_amount": 30000
      }
    ],
    "week_daily_sales": [
      { "date": "2026-07-07", "sales_amount": 3000, "order_count": 1 },
      { "date": "2026-07-08", "sales_amount": 0, "order_count": 0 }
    ]
  }
}
```

### 5.2 状态中文标签

| status | label |
|--------|-------|
| `pending_payment` | 待支付 |
| `paid` | 已支付 |
| `preparing` | 备餐中 |
| `ready` | 可取餐 |
| `completed` | 已完成 |
| `cancelled` | 已取消 |

### 5.3 目录结构

```
backend/app/
├── Application/Dashboard/
│   ├── DTO/DashboardStatsDto.php
│   └── GetDashboardStats/GetDashboardStatsHandler.php
├── Domain/Dashboard/
│   └── Repositories/DashboardStatsRepositoryInterface.php
├── Infrastructure/Persistence/Eloquent/
│   └── EloquentDashboardStatsRepository.php
└── Http/Controllers/Admin/
    └── DashboardController.php

backend/tests/
├── Unit/Application/Dashboard/GetDashboardStatsHandlerTest.php
├── Feature/Admin/DashboardStatsApiTest.php
└── Feature/Infrastructure/EloquentDashboardStatsRepositoryTest.php
```

### 5.4 仓储接口

```php
interface DashboardStatsRepositoryInterface
{
    /**
     * @return array{
     *   summary: array{today: array, week: array},
     *   status_distribution: list<array{status: string, label: string, count: int}>,
     *   hot_products_by_quantity: list<array>,
     *   hot_products_by_sales: list<array>,
     *   week_daily_sales: list<array{date: string, sales_amount: int, order_count: int}>
     * }
     */
    public function getStats(): array;
}
```

Handler 调用仓储并包装为 `DashboardStatsDto`；Controller 直接 `ApiResponse::success($dto->toArray())`。

### 5.5 服务注册

在 `AppServiceProvider` 绑定 `DashboardStatsRepositoryInterface` → `EloquentDashboardStatsRepository`。

---

## 6. Frontend 实现要点

### 6.1 依赖

```bash
cd frontend && npm install recharts@^2.15.0
```

### 6.2 API 层

`dashboardApi.getStats()` → `GET /admin/dashboard/stats`，复用现有 `api/client.ts` 鉴权 fetch。

### 6.3 DashboardPage

- 加载态：`Spin` 包裹整页
- 错误态：`message.error` + 重试按钮
- 金额展示：复用 `fenToYuan`
- 饼图：`status_distribution`，`label` 作图例
- 柱状图：`week_daily_sales`，X 轴 `date`（格式 `MM-DD`），Y 轴金额（元）
- 热门商品表：列 = 排名 / 商品名 / 销量或销售额

### 6.4 AdminLayout 变更

- 菜单首位：`DashboardOutlined` + 「数据统计」
- `selectedKey`：`pathname.startsWith('/dashboard')` → `'dashboard'`
- `App.tsx`：`index` redirect 改为 `/dashboard`

---

## 7. 测试策略

### 7.1 Unit — `GetDashboardStatsHandlerTest`

Mock `DashboardStatsRepositoryInterface`，验证 Handler 透传并返回 DTO 结构。

### 7.2 Integration — `EloquentDashboardStatsRepositoryTest`

`RefreshDatabase` + 手工创建订单：

- 今日创建 2 单（1 待支付 + 1 已支付）→ `today.order_count = 2`，`paid_order_count = 1`
- 本周已支付 3 单总额 9000 分 → `week.sales_amount = 9000`
- 2 个商品不同销量 → 销量榜排序正确
- 状态分布计数与 DB 一致
- `week_daily_sales` 无数据日补 0

### 7.3 Feature — `DashboardStatsApiTest`

| 用例 | 期望 |
|------|------|
| admin token 访问 | 200，`code = 0`，结构完整 |
| employee token 访问 | 403 |
| 无 token | 401 |

### 7.4 前端手工验收

- [ ] 登录后默认进入 `/dashboard`
- [ ] 4 张指标卡片数值与 API 一致
- [ ] 饼图、柱状图正常渲染
- [ ] 双热门商品表各 5 行（或不足 5 时显示实际数量）
- [ ] `php artisan db:seed --class=OrderSeeder` 后数据与订单表可对账

---

## 8. 验收标准（对齐总体 spec）

- [ ] 展示今日/本周关键指标（订单数 + 销售额）
- [ ] 热门商品 Top 5（销量 + 销售额双榜）
- [ ] 订单状态分布图表
- [ ] 数据与 `orders` / `order_items` 表一致（seed 可验证）
- [ ] `./scripts/docker-test.sh` 全绿

---

## 9. 预估

**1 人天**（与总体 spec 一致）
