# 联调自动化测试 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-13  
> **前置依赖：** 全部后端 API 已上线（M01-M15 已完成）  
> **测试类型：** Feature 测试（场景串联，API 层面）

---

## 1. 目标

在 **backend** 层编写场景串联 Feature 测试，模拟完整的用户业务流程，覆盖 APP 和管理端的核心操作链路。

**测试原则：**
- 全部基于 `RefreshDatabase` + sqlite `:memory:`，不依赖 Docker MySQL
- 每个场景测试 = 一个 `#[Test]` 方法内连续调用多个 API，模拟真实用户操作
- 沿用现有代码风格（`withToken`、`postJson`/`getJson`、`assertJsonPath`）
- 不使用浏览器 E2E 或移动端模拟器

**非目标（本次不做）：**
- 忘记密码 → 重置密码功能（用户确认不需要）
- 前端浏览器 E2E 测试（Playwright）
- APP 端 Jest 增强
- CI 管道配置

---

## 2. 文件结构

```
backend/tests/Feature/Scenarios/
├── AppAuthFlowTest.php         # APP 认证全流程
├── AppFlowTest.php             # APP 下单自付全流程
├── AppProxyPayFlowTest.php     # APP 代付全流程
└── AdminFlowTest.php           # 管理端全流程
```

### 命名规范

| 项目 | 规范 |
|------|------|
| 命名空间 | `Tests\Feature\Scenarios` |
| 测试方法名 | 一个方法描述一个完整场景，如 `employee_complete_shopping_flow` |
| 测试方法内步骤 | 用注释标注步骤编号 `// 1. xxx` `// 2. xxx` |
| 辅助方法 | `private function`，与测试方法同级 |
| 断言风格 | 每个 API 调用后立即 `assertOk/assertCreated/assertJsonPath` |

---

## 3. 场景测试详细设计

### 3.1 `AppAuthFlowTest.php` — APP 认证全流程

**数据准备：** 已注册员工（`must_change_password=true`，密码 `123456`）

**测试步骤：**

| # | 操作 | API | 关键断言 |
|---|------|-----|----------|
| 1 | 登录 | `POST /api/v1/auth/login` | `code=0`, `must_change_password=true` |
| 2 | 修改密码 | `PUT /api/v1/auth/password` | `code=0` |
| 3 | 查看个人信息 | `GET /api/v1/auth/me` | `phone` 匹配，`must_change_password=false` |
| 4 | 退出登录 | `POST /api/v1/auth/logout` | `code=0` |
| 5 | 用旧密码登录（应失败） | `POST /api/v1/auth/login` | `401` |
| 6 | 用新密码重新登录 | `POST /api/v1/auth/login` | `code=0`, `must_change_password=false` |

```php
#[Test]
public function employee_auth_flow(): void
{
    // 1. 创建员工（must_change_password=true）
    $employee = UserModel::factory()->mustChangePassword()->create([
        'phone' => '13800001001',
        'password' => Hash::make('old_password'),
    ]);

    // 2. 登录
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'phone' => '13800001001',
        'password' => 'old_password',
    ])->assertOk()
        ->assertJsonPath('data.must_change_password', true);
    $token = $loginResponse->json('data.token');

    // 3. 修改密码
    $this->withToken($token)
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'old_password',
            'new_password' => 'new_password',
            'new_password_confirmation' => 'new_password',
        ])->assertOk();

    // 4. 查个人信息
    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.phone', '13800001001');

    // 5. 退出
    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // 6. 旧密码登录失败
    $this->postJson('/api/v1/auth/login', [
        'phone' => '13800001001',
        'password' => 'old_password',
    ])->assertUnauthorized();

    // 7. 新密码重新登录
    $this->postJson('/api/v1/auth/login', [
        'phone' => '13800001001',
        'password' => 'new_password',
    ])->assertOk()
        ->assertJsonPath('data.must_change_password', false);
}
```

---

### 3.2 `AppFlowTest.php` — APP 下单自付全流程

**数据准备：** 管理员已创建 2 个分类（各含 1 个上架商品）

**测试步骤（单次 `#[Test]` 内串联）：**

| # | 操作 | API | 关键断言 |
|---|------|-----|----------|
| 1 | 员工登录 | `POST /api/v1/auth/login` | `code=0` |
| 2 | 查看分类列表 | `GET /api/v1/categories` | 返回 active 分类，不返回 disabled |
| 3 | 按分类查看商品 | `GET /api/v1/products?category_id={id}` | 仅返回该分类的商品 |
| 4 | 查看商品详情 | `GET /api/v1/products/{id}` | 名称、价格、图片正确 |
| 5 | 创建订单 | `POST /api/v1/orders` | `status=pending_payment`, `total_amount` 正确 |
| 6 | 查看订单详情 | `GET /api/v1/orders/{id}` | 状态一致，items 含商品名 |
| 7 | 发起支付 | `POST /api/v1/orders/{id}/pay` | `payment.status=pending` |
| 8 | 模拟通知回调 | `POST /api/v1/payments/notify/alipay` | 返回 `success` |
| 9 | 查看订单状态 | `GET /api/v1/orders/{id}` | `status=paid` |

辅助方法：

```php
private function setUpCatalog(): array
{
    $category1 = CategoryModel::factory()->create(['name' => '饮品', 'sort' => 1]);
    $category2 = CategoryModel::factory()->create(['name' => '甜点', 'sort' => 2]);
    CategoryModel::factory()->disabled()->create(['name' => '已禁用']);
    $product1 = ProductModel::factory()->onSale()->create([
        'category_id' => $category1->id, 'name' => '拿铁', 'price' => 1500,
    ]);
    $product2 = ProductModel::factory()->onSale()->create([
        'category_id' => $category2->id, 'name' => '蛋糕', 'price' => 2000,
    ]);
    return [$category1, $category2, $product1, $product2];
}
```

---

### 3.3 `AppProxyPayFlowTest.php` — APP 代付全流程

**数据准备：** 管理员已创建分类 + 商品

**测试步骤：**

| # | 操作 | API | 关键断言 |
|---|------|-----|----------|
| 1 | 员工登录 | `POST /api/v1/auth/login` | `code=0` |
| 2 | 下单（代付模式） | `POST /api/v1/orders` with `payment_method=proxy` | `status=pending_payment` |
| 3 | 生成代付链接 | `POST /api/v1/orders/{id}/proxy-pay-link` | 返回 `url` 含 `/proxy-pay/` |
| 4 | 代付人预览（公开） | `GET /api/v1/proxy-pay/{token}` | `payable=true`，买家名脱敏 |
| 5 | 代付人发起支付 | `POST /api/v1/proxy-pay/{token}/pay` | `payment.status=pending` |
| 6 | 回调通知 | `POST /api/v1/payments/notify/wechat` | 返回 `success` |
| 7 | 验证订单已支付 | `GET /api/v1/orders/{id}` | `status=paid`，`paid_by_external_user_id` 不为空 |

**重点验证：** 代付模式下自付被拦截（`POST /api/v1/orders/{id}/pay` 返回 422）

```php
#[Test]
public function proxy_pay_flow(): void
{
    // ... setup ...
    
    // 下单（代付模式）
    $orderResponse = $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'proxy',
        ])->assertCreated();
    $orderId = $orderResponse->json('data.id');

    // 自付应被拦截
    $this->withToken($token)
        ->postJson("/api/v1/orders/{$orderId}/pay")
        ->assertStatus(422);

    // 生成代付链接
    $linkResponse = $this->withToken($token)
        ->postJson("/api/v1/orders/{$orderId}/proxy-pay-link");
    $proxyToken = $linkResponse->json('data.url');
    $this->assertStringContainsString('/proxy-pay/', $proxyToken);
    // 提取 token 值
    preg_match('/\/proxy-pay\/(\w+)/', $proxyToken, $matches);
    $tokenValue = $matches[1];

    // 代付人预览
    $this->getJson("/api/v1/proxy-pay/{$tokenValue}")
        ->assertOk()
        ->assertJsonPath('data.payable', true);

    // 代付人支付
    $payResponse = $this->postJson("/api/v1/proxy-pay/{$tokenValue}/pay", [
        'channel' => 'fake',
        'provider' => 'fake',
        'payer_name' => '代付人',
    ])->assertOk();
    $outTradeNo = $payResponse->json('data.payment.out_trade_no');

    // 回调
    $this->postJson('/api/v1/payments/notify/wechat', [
        'trade_status' => 'TRADE_SUCCESS',
        'out_trade_no' => $outTradeNo,
        'trade_no' => 'PROXY_SCENARIO',
    ])->assertOk();

    // 验证支付成功
    $this->withToken($token)
        ->getJson("/api/v1/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');
}
```

---

### 3.4 `AdminFlowTest.php` — 管理端全流程

**数据准备：** 管理员已存在（通过 Factory 创建）

**测试步骤：**

| # | 操作 | API | 关键断言 |
|---|------|-----|----------|
| 1 | 管理员登录 | `POST /api/v1/auth/login` | `code=0`, `token` 存在 |
| 2 | 添加分类 | `POST /api/v1/admin/categories` | `status=active` |
| 3 | 添加商品（含 upload_id） | `POST /api/v1/admin/products` | 价格、名称正确 |
| 4 | 添加员工 | `POST /api/v1/admin/employees` | `must_change_password=true` |
| 5 | 重置员工密码 | `PUT /api/v1/admin/employees/{id}` with `reset_password=true` | `must_change_password=true` |
| 6 | 获取配置列表 | `GET /api/v1/admin/configs` | 返回 groups 结构 |
| 7 | 修改非敏感配置 | `PUT /api/v1/admin/configs` | `code=0` |
| 8 | 验证配置已更新 | `GET /api/v1/admin/configs` | 值已更新 |
| 9 | 查看仪表盘统计 | `GET /api/v1/admin/dashboard/stats` | `code=0` |

```php
#[Test]
public function admin_complete_management_flow(): void
{
    // 1. 管理员登录
    $admin = UserModel::factory()->admin()->create(['password' => Hash::make('admin123')]);
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'phone' => $admin->phone,
        'password' => 'admin123',
    ])->assertOk();
    $token = $loginResponse->json('data.token');

    // 2. 添加分类
    $categoryResponse = $this->withToken($token)
        ->postJson('/api/v1/admin/categories', ['name' => '饮品', 'sort' => 1])
        ->assertCreated();
    $categoryId = $categoryResponse->json('data.id');

    // 3. 添加上传记录 + 商品
    $upload = UploadModel::factory()->create();
    $productResponse = $this->withToken($token)
        ->postJson('/api/v1/admin/products', [
            'category_id' => $categoryId,
            'name' => '拿铁',
            'price' => 1500,
            'upload_id' => $upload->id,
            'status' => 'on_sale',
        ])->assertCreated()
        ->assertJsonPath('data.name', '拿铁');

    // 4. 添加员工
    $employeeResponse = $this->withToken($token)
        ->postJson('/api/v1/admin/employees', [
            'name' => '张三',
            'phone' => '13890000099',
        ])->assertCreated()
        ->assertJsonPath('data.phone', '13890000099');
    $employeeId = $employeeResponse->json('data.id');

    // 5. 重置员工密码
    $this->withToken($token)
        ->putJson("/api/v1/admin/employees/{$employeeId}", [
            'name' => '张三',
            'role' => 'employee',
            'status' => 'active',
            'reset_password' => true,
        ])->assertOk()
        ->assertJsonPath('data.must_change_password', true);

    // 6. 获取配置
    $this->withToken($token)
        ->getJson('/api/v1/admin/configs')
        ->assertOk()
        ->assertJsonPath('code', 0);

    // 7. 修改非敏感配置
    $this->withToken($token)
        ->putJson('/api/v1/admin/configs', [
            'configs' => [['group' => 'app', 'key' => 'name', 'value' => '测试店铺']],
        ])->assertOk();

    // 8. 验证配置已更新
    $configResponse = $this->withToken($token)
        ->getJson('/api/v1/admin/configs')
        ->assertOk();
    $appGroup = collect($configResponse->json('data.groups'))
        ->firstWhere('name', 'app');
    $nameItem = collect($appGroup['items'])->firstWhere('key', 'name');
    $this->assertSame('测试店铺', $nameItem['value']);

    // 9. 查看仪表盘
    $this->withToken($token)
        ->getJson('/api/v1/admin/dashboard/stats')
        ->assertOk();
}
```

---

## 4. 数据准备策略

所有场景测试使用 `RefreshDatabase` trait，通过 Factory 创建测试数据，**不依赖 Seeder**。

| 数据类型 | 创建方式 |
|----------|----------|
| 管理员 | `UserModel::factory()->admin()->create()` |
| 超级管理员 | `UserModel::factory()->superAdmin()->create()` |
| 员工 | `UserModel::factory()->create(['role' => 'employee'])` |
| 需改密员工 | `UserModel::factory()->mustChangePassword()->create()` |
| 分类 | `CategoryModel::factory()->create()` |
| 上架商品 | `ProductModel::factory()->onSale()->create()` |
| 上传记录 | `UploadModel::factory()->create()` |

### `setUp()` 注意事项

| 测试类 | `setUp()` 需求 |
|--------|----------------|
| `AppFlowTest` | 调用 `$this->seedLocalStoragePublicBaseUrl()`（商品图片需要） |
| `AppProxyPayFlowTest` | 配置 `app.frontend_url` + `order.auto_cancel_minutes` + `payment.provider=fake` |
| `AdminFlowTest` | 不需要特殊 setUp |
| `AppAuthFlowTest` | 不需要特殊 setUp |

---

## 5. 验证策略

| 维度 | 方法 |
|------|------|
| API 响应 | `assertOk()` / `assertCreated()` + `assertJsonPath()` |
| 状态流转 | 订单状态 `pending_payment → paid`，密码 `must_change_password=true → false` |
| 数据库记录 | 可选 `assertDatabaseHas()` / `$model->fresh()` |
| 权限边界 | 代付模式下自付被拦截（422）；旧密码登录被拒（401） |
| Token 生命周期 | 退出后 token 失效，新密码登录获取新 token |

---

## 6. 测试运行

```bash
# 启动 Docker 环境
./scripts/dev-up.sh

# 仅运行场景测试
docker compose exec backend php artisan test --filter=Scenarios

# 单文件
docker compose exec backend php artisan test --filter=AppFlowTest

# 单场景方法
docker compose exec backend php artisan test --filter=employee_complete_shopping_flow

# 全量测试（含已有 71 个测试）
./scripts/docker-test.sh
```

---

## 7. 范围 / 非范围

### 范围内

- ✅ 4 个场景测试文件，覆盖 APP 认证、下单自付、代付、管理端全流程
- ✅ 重用现有 Factory/Model/API 端点
- ✅ 遵循现有测试约定（`RefreshDatabase`、`#[Test]`、sqlite）

### 范围外

- ❌ 忘记密码功能（前端或后端）
- ❌ 前端浏览器 E2E 测试
- ❌ APP Jest 测试增强
- ❌ CI 管道配置
- ❌ 覆盖率达到特定百分比
