# M15 管理后台数据统计 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现管理后台数据统计仪表盘：单一聚合 API + Dashboard 页面（指标卡片、recharts 图表、双热门商品榜），默认首页 `/dashboard`。

**Architecture:** Backend 新增 Dashboard 读模型：`DashboardStatsRepositoryInterface` 封装 SQL 聚合，`GetDashboardStatsHandler` 组装 DTO，`DashboardController` 暴露 `GET /admin/dashboard/stats`。Frontend 新增 `DashboardPage`，复用 Ant Design + 现有 `api/client.ts` 鉴权。

**Tech Stack:** React **18.3.1** · TypeScript **~5.6** · Vite **5.x** · Ant Design **5.x** · recharts **^2.15** · Laravel **12** · PHP **8.4** · MySQL **8.0**

## Global Constraints

- React **18.3.1**（禁止 17 / 18.0~18.2 / 19）
- PHP **8.4** / Laravel **12**（Backend 测试在 Docker 内：`./scripts/docker-test.sh`）
- API 前缀 `/api/v1/`，响应 `{ "code": 0, "message": "ok", "data": {} }`
- 金额整数分存储；前端 `fenToYuan()` 展示
- DDD 分层 + TDD；无测试不得合并
- Frontend 不直连数据库；API 放 `frontend/src/api/`
- 统计时区 **`Asia/Shanghai`**（仓储内 `Carbon::now('Asia/Shanghai')` 计算边界，不依赖 `config/app.php` 的 UTC）
- 假定 M11/M13 已合并（AdminLayout、AuthContext、`api/client.ts`、`fenToYuan` 可用）

**Design spec:** `docs/superpowers/specs/2026-07-12-M15-admin-dashboard-design.md`

---

### Task 1: Dashboard 仓储接口与集成测试（TDD）

**Files:**
- Create: `backend/app/Domain/Dashboard/Repositories/DashboardStatsRepositoryInterface.php`
- Create: `backend/tests/Feature/Infrastructure/EloquentDashboardStatsRepositoryTest.php`

**Interfaces:**
- Produces: `DashboardStatsRepositoryInterface::getStats(): array` — 返回 spec §5.1 完整结构

- [ ] **Step 1: Write failing integration test**

```php
<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentDashboardStatsRepository;
use App\Infrastructure\Persistence\Eloquent\Models\OrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentDashboardStatsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStatsRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-12 14:30:00', 'Asia/Shanghai'));
        $this->repository = new EloquentDashboardStatsRepository();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function summary_counts_today_and_week_correctly(): void
    {
        $user = UserModel::factory()->create();

        // 今日：1 待支付 + 1 已支付 6000 分
        OrderModel::factory()->for($user, 'user')->create([
            'created_at' => Carbon::parse('2026-07-12 10:00:00', 'Asia/Shanghai'),
        ]);
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 6000,
            'paid_at' => Carbon::parse('2026-07-12 11:00:00', 'Asia/Shanghai'),
            'created_at' => Carbon::parse('2026-07-12 11:00:00', 'Asia/Shanghai'),
        ]);

        // 本周早些时候：1 已支付 3000 分
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 3000,
            'paid_at' => Carbon::parse('2026-07-07 09:00:00', 'Asia/Shanghai'),
            'created_at' => Carbon::parse('2026-07-07 09:00:00', 'Asia/Shanghai'),
        ]);

        // 上周：不计入本周
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 9999,
            'paid_at' => Carbon::parse('2026-07-05 09:00:00', 'Asia/Shanghai'),
            'created_at' => Carbon::parse('2026-07-05 09:00:00', 'Asia/Shanghai'),
        ]);

        $stats = $this->repository->getStats();

        $this->assertSame(2, $stats['summary']['today']['order_count']);
        $this->assertSame(1, $stats['summary']['today']['paid_order_count']);
        $this->assertSame(6000, $stats['summary']['today']['sales_amount']);
        $this->assertSame(3, $stats['summary']['week']['order_count']);
        $this->assertSame(2, $stats['summary']['week']['paid_order_count']);
        $this->assertSame(9000, $stats['summary']['week']['sales_amount']);
    }

    #[Test]
    public function hot_products_rank_by_quantity_and_sales(): void
    {
        $user = UserModel::factory()->create();
        $latte = ProductModel::factory()->create(['name' => '拿铁', 'price' => 1500]);
        $mocha = ProductModel::factory()->create(['name' => '摩卡', 'price' => 2000]);

        $order = OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 11000,
            'paid_at' => Carbon::parse('2026-07-10 12:00:00', 'Asia/Shanghai'),
        ]);

        OrderItemModel::factory()->for($order, 'order')->create([
            'product_id' => $latte->id,
            'product_name' => '拿铁',
            'price' => 1500,
            'quantity' => 5,
            'subtotal' => 7500,
        ]);
        OrderItemModel::factory()->for($order, 'order')->create([
            'product_id' => $mocha->id,
            'product_name' => '摩卡',
            'price' => 2000,
            'quantity' => 2,
            'subtotal' => 4000,
        ]);

        $stats = $this->repository->getStats();

        $this->assertSame('拿铁', $stats['hot_products_by_quantity'][0]['product_name']);
        $this->assertSame(5, $stats['hot_products_by_quantity'][0]['quantity']);
        $this->assertSame('拿铁', $stats['hot_products_by_sales'][0]['product_name']);
        $this->assertSame(7500, $stats['hot_products_by_sales'][0]['sales_amount']);
    }

    #[Test]
    public function status_distribution_counts_all_orders(): void
    {
        $user = UserModel::factory()->create();
        OrderModel::factory()->for($user, 'user')->paid()->count(2)->create();
        OrderModel::factory()->for($user, 'user')->cancelled()->create();

        $stats = $this->repository->getStats();
        $byStatus = collect($stats['status_distribution'])->keyBy('status');

        $this->assertSame(2, $byStatus['paid']['count']);
        $this->assertSame(1, $byStatus['cancelled']['count']);
        $this->assertSame('已支付', $byStatus['paid']['label']);
    }

    #[Test]
    public function week_daily_sales_fills_missing_days_with_zero(): void
    {
        $user = UserModel::factory()->create();
        OrderModel::factory()->for($user, 'user')->paid()->create([
            'total_amount' => 3000,
            'paid_at' => Carbon::parse('2026-07-10 12:00:00', 'Asia/Shanghai'),
        ]);

        $stats = $this->repository->getStats();

        $this->assertCount(7, $stats['week_daily_sales']);
        $byDate = collect($stats['week_daily_sales'])->keyBy('date');
        $this->assertSame(3000, $byDate['2026-07-10']['sales_amount']);
        $this->assertSame(0, $byDate['2026-07-08']['sales_amount']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./scripts/docker-test.sh --filter=EloquentDashboardStatsRepositoryTest
```

Expected: FAIL — class `EloquentDashboardStatsRepository` not found

- [ ] **Step 3: Create interface**

```php
<?php

namespace App\Domain\Dashboard\Repositories;

interface DashboardStatsRepositoryInterface
{
    /**
     * @return array{
     *   summary: array{
     *     today: array{order_count: int, paid_order_count: int, sales_amount: int},
     *     week: array{order_count: int, paid_order_count: int, sales_amount: int}
     *   },
     *   status_distribution: list<array{status: string, label: string, count: int}>,
     *   hot_products_by_quantity: list<array{product_id: int, product_name: string, quantity: int, sales_amount: int}>,
     *   hot_products_by_sales: list<array{product_id: int, product_name: string, quantity: int, sales_amount: int}>,
     *   week_daily_sales: list<array{date: string, sales_amount: int, order_count: int}>
     * }
     */
    public function getStats(): array;
}
```

- [ ] **Step 4: Implement `EloquentDashboardStatsRepository`**

Create `backend/app/Infrastructure/Persistence/Eloquent/EloquentDashboardStatsRepository.php`:

- 私有常量 `PAID_STATUSES = ['paid','preparing','ready','completed']`
- 私有常量 `STATUS_LABELS` 映射（spec §5.2）
- `now()` = `Carbon::now('Asia/Shanghai')`
- `todayStart()` / `weekStart()` = 今日 00:00 / 本周一 00:00
- `paidOrdersQuery()` = `OrderModel::query()->whereIn('status', PAID_STATUSES)->whereNotNull('paid_at')`
- summary：`order_count` 用 `created_at` 范围；paid 指标用 `paid_at` 范围
- `status_distribution`：`OrderModel::query()->selectRaw('status, count(*) as count')->groupBy('status')`
- hot products：join `order_items`，filter 本周已支付订单，`groupBy product_id, product_name`，分别 `orderByDesc SUM(quantity)` / `orderByDesc SUM(subtotal)`，`limit(5)`
- `week_daily_sales`：本周每天 loop 7 天（周一~周日），每天查 paid 订单 sum；无数据填 0

- [ ] **Step 5: Run integration tests**

```bash
./scripts/docker-test.sh --filter=EloquentDashboardStatsRepositoryTest
```

Expected: PASS (4 tests)

---

### Task 2: Application 层 + Handler 单元测试

**Files:**
- Create: `backend/app/Application/Dashboard/DTO/DashboardStatsDto.php`
- Create: `backend/app/Application/Dashboard/GetDashboardStats/GetDashboardStatsHandler.php`
- Create: `backend/tests/Unit/Application/Dashboard/GetDashboardStatsHandlerTest.php`

**Interfaces:**
- Consumes: `DashboardStatsRepositoryInterface::getStats(): array`
- Produces: `GetDashboardStatsHandler::handle(): DashboardStatsDto` — `toArray()` 返回 API data 结构

- [ ] **Step 1: Write failing unit test**

```php
<?php

namespace Tests\Unit\Application\Dashboard;

use App\Application\Dashboard\GetDashboardStats\GetDashboardStatsHandler;
use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetDashboardStatsHandlerTest extends TestCase
{
    #[Test]
    public function handle_returns_dto_from_repository(): void
    {
        $repo = $this->createMock(DashboardStatsRepositoryInterface::class);
        $repo->method('getStats')->willReturn([
            'summary' => [
                'today' => ['order_count' => 1, 'paid_order_count' => 1, 'sales_amount' => 100],
                'week' => ['order_count' => 2, 'paid_order_count' => 1, 'sales_amount' => 100],
            ],
            'status_distribution' => [['status' => 'paid', 'label' => '已支付', 'count' => 1]],
            'hot_products_by_quantity' => [],
            'hot_products_by_sales' => [],
            'week_daily_sales' => [],
        ]);

        $handler = new GetDashboardStatsHandler($repo);
        $dto = $handler->handle();

        $this->assertSame(1, $dto->toArray()['summary']['today']['order_count']);
        $this->assertSame('已支付', $dto->toArray()['status_distribution'][0]['label']);
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
./scripts/docker-test.sh --filter=GetDashboardStatsHandlerTest
```

- [ ] **Step 3: Implement DTO and Handler**

`DashboardStatsDto.php` — 构造函数接收 repository 返回的 array，`toArray(): array` 原样返回。

`GetDashboardStatsHandler.php`:

```php
public function handle(): DashboardStatsDto
{
    return new DashboardStatsDto($this->repository->getStats());
}
```

- [ ] **Step 4: Run unit test — expect PASS**

```bash
./scripts/docker-test.sh --filter=GetDashboardStatsHandlerTest
```

---

### Task 3: HTTP 层 + Feature 测试 + 服务注册

**Files:**
- Create: `backend/app/Http/Controllers/Admin/DashboardController.php`
- Create: `backend/tests/Feature/Admin/DashboardStatsApiTest.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`

**Interfaces:**
- Produces: `GET /api/v1/admin/dashboard/stats` → 200 + spec JSON 结构

- [ ] **Step 1: Write failing feature test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardStatsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return UserModel::factory()->admin()->create()->createToken('test')->plainTextToken;
    }

    #[Test]
    public function admin_can_get_dashboard_stats(): void
    {
        OrderModel::factory()->paid()->create();

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['today', 'week'],
                    'status_distribution',
                    'hot_products_by_quantity',
                    'hot_products_by_sales',
                    'week_daily_sales',
                ],
            ]);
    }

    #[Test]
    public function employee_cannot_access_dashboard_stats(): void
    {
        $token = UserModel::factory()->create(['role' => 'employee'])->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertForbidden();
    }

    #[Test]
    public function guest_cannot_access_dashboard_stats(): void
    {
        $this->getJson('/api/v1/admin/dashboard/stats')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run — expect FAIL** (404 or route not found)

```bash
./scripts/docker-test.sh --filter=DashboardStatsApiTest
```

- [ ] **Step 3: Implement Controller + Route + binding**

`DashboardController.php`:

```php
public function stats(GetDashboardStatsHandler $handler): JsonResponse
{
    return ApiResponse::success($handler->handle()->toArray());
}
```

`api.php` admin group 内添加：

```php
use App\Http\Controllers\Admin\DashboardController;
// ...
Route::get('dashboard/stats', [DashboardController::class, 'stats']);
```

`AppServiceProvider.php` register():

```php
use App\Domain\Dashboard\Repositories\DashboardStatsRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentDashboardStatsRepository;
// ...
$this->app->bind(DashboardStatsRepositoryInterface::class, EloquentDashboardStatsRepository::class);
```

- [ ] **Step 4: Run feature tests — expect PASS**

```bash
./scripts/docker-test.sh --filter=DashboardStatsApiTest
```

---

### Task 4: Frontend API 层与类型

**Files:**
- Create: `frontend/src/types/dashboard.ts`
- Create: `frontend/src/api/dashboard.ts`
- Modify: `frontend/package.json`（添加 recharts 依赖）

**Interfaces:**
- Produces: `dashboardApi.getStats(): Promise<DashboardStats>`

- [ ] **Step 1: Install recharts**

```bash
cd frontend && npm install recharts@^2.15.0
```

- [ ] **Step 2: Create types**

```typescript
// frontend/src/types/dashboard.ts

export interface DashboardSummaryPeriod {
  order_count: number;
  paid_order_count: number;
  sales_amount: number;
}

export interface StatusDistributionItem {
  status: string;
  label: string;
  count: number;
}

export interface HotProductItem {
  product_id: number;
  product_name: string;
  quantity: number;
  sales_amount: number;
}

export interface DailySalesItem {
  date: string;
  sales_amount: number;
  order_count: number;
}

export interface DashboardStats {
  summary: {
    today: DashboardSummaryPeriod;
    week: DashboardSummaryPeriod;
  };
  status_distribution: StatusDistributionItem[];
  hot_products_by_quantity: HotProductItem[];
  hot_products_by_sales: HotProductItem[];
  week_daily_sales: DailySalesItem[];
}
```

- [ ] **Step 3: Create API module**

```typescript
// frontend/src/api/dashboard.ts
import { request } from './client';
import type { DashboardStats } from '../types/dashboard';

export const dashboardApi = {
  getStats(): Promise<DashboardStats> {
    return request<DashboardStats>('/admin/dashboard/stats');
  },
};
```

- [ ] **Step 4: Verify TypeScript compiles**

```bash
cd frontend && npm run build
```

Expected: PASS (no type errors)

---

### Task 5: DashboardPage 与路由集成

**Files:**
- Create: `frontend/src/pages/dashboard/DashboardPage.tsx`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/components/AdminLayout.tsx`

**Interfaces:**
- Consumes: `dashboardApi.getStats(): Promise<DashboardStats>`
- Produces: `/dashboard` 路由 + 侧边栏「数据统计」菜单项

- [ ] **Step 1: Create DashboardPage**

`DashboardPage.tsx` 要点：

- `useState` loading/error/data
- `useEffect` 调用 `dashboardApi.getStats()`
- 顶部 `Row` + 4× `Col` + `Statistic`：今日订单、今日销售额、本周订单、本周销售额
- `ResponsiveContainer` + `BarChart`：`week_daily_sales`，Y 轴 `fenToYuan`，X 轴 `date` 格式化为 `MM-DD`
- `PieChart`：`status_distribution`，`nameKey="label"` `dataKey="count"`
- 两个 `Table`：热门商品销量榜 / 销售额榜（列：排名、商品名、销量或销售额）
- 饼图颜色：复用 `OrderListPage` 的 `STATUS_COLORS` 映射（或内联常量）
- 空数据：`Empty` 组件友好提示

- [ ] **Step 2: Update App.tsx**

```typescript
import DashboardPage from './pages/dashboard/DashboardPage';

// admin routes:
<Route index element={<Navigate to="/dashboard" replace />} />
<Route path="dashboard" element={<DashboardPage />} />
```

- [ ] **Step 3: Update AdminLayout.tsx**

- import `DashboardOutlined`
- 菜单数组**首位**添加 `{ key: 'dashboard', icon: <DashboardOutlined />, label: '数据统计', onClick: () => navigate('/dashboard') }`
- `selectedKey` 逻辑：`pathname.startsWith('/dashboard')` → `'dashboard'`（放在最前）

- [ ] **Step 4: Build frontend**

```bash
cd frontend && npm run build
```

Expected: PASS

---

### Task 6: 全量验证

**Files:**（无新文件）

- [ ] **Step 1: Run full backend test suite**

```bash
./scripts/docker-test.sh
```

Expected: ALL PASS

- [ ] **Step 2: Manual acceptance checklist**

- [ ] 登录后默认进入 `/dashboard`
- [ ] 4 张指标卡片数值合理
- [ ] 柱状图 + 饼图正常渲染
- [ ] 双热门商品表显示正确
- [ ] `docker compose exec backend php artisan db:seed --class=OrderSeeder` 后刷新页面，数据与订单表可对账

---

## Plan Self-Review

| Spec 要求 | 对应 Task |
|-----------|-----------|
| `GET /api/v1/admin/dashboard/stats` | Task 1–3 |
| 今日/本周指标 | Task 1 集成测试 + 仓储实现 |
| 热门商品双榜 Top 5 | Task 1 + Task 5 表格 |
| 订单状态分布 | Task 1 + Task 5 饼图 |
| recharts 图表 | Task 4 + Task 5 |
| `/dashboard` 默认首页 | Task 5 |
| 后端 TDD 测试 | Task 1–3 |
| 前端手工验收 | Task 6 |

无 TBD / 占位符；类型与接口在各 Task **Interfaces** 块一致。
