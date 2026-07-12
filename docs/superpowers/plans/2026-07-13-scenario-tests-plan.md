# Scenario Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create 4 scenario-based Feature tests covering APP and Admin full business flows at the API integration level.

**Architecture:** 4 new test files in `backend/tests/Feature/Scenarios/`, each containing one `#[Test]` method that chains multiple API calls to simulate a complete user journey. No new production code needed.

**Tech Stack:** PHPUnit 11.5, Laravel 12, sqlite `:memory:`, RefreshDatabase

## Global Constraints

- All tests use `RefreshDatabase` + sqlite `:memory:`
- All tests extend `Tests\TestCase`
- All test methods use `#[Test]` attribute (PHPUnit 11 style)
- Never run against Docker MySQL — only `./scripts/docker-test.sh`
- Never use `migrate:fresh` / `migrate:refresh` / `db:wipe`

---

### Task 1: `AppAuthFlowTest.php` — APP 认证全流程

**Files:**
- Create: `backend/tests/Feature/Scenarios/AppAuthFlowTest.php`

**Interfaces:**
- Consumes: `UserModel::factory()->mustChangePassword()`, `Hash::make()`, `/api/v1/auth/*` endpoints
- Produces: a passing scenario test for login → change password → me → logout → re-login

- [ ] **Step 1: Write the test file**

Content of `backend/tests/Feature/Scenarios/AppAuthFlowTest.php`:

```php
<?php

namespace Tests\Feature\Scenarios;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_auth_flow(): void
    {
        $phone = '13800001001';
        $oldPassword = 'old_password';
        $newPassword = 'new_password';

        // 1. 创建需改密员工
        UserModel::factory()->mustChangePassword()->create([
            'phone' => $phone,
            'password' => Hash::make($oldPassword),
        ]);

        // 2. 登录 → 拿到 token + must_change_password=true
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $phone,
            'password' => $oldPassword,
        ])->assertOk()
            ->assertJsonPath('data.must_change_password', true);
        $token = $loginResponse->json('data.token');

        // 3. 修改密码
        $this->withToken($token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => $oldPassword,
                'new_password' => $newPassword,
                'new_password_confirmation' => $newPassword,
            ])->assertOk();

        // 4. 查看个人信息
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.phone', $phone);

        // 5. 退出登录
        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // 6. 旧密码登录失败
        $this->postJson('/api/v1/auth/login', [
            'phone' => $phone,
            'password' => $oldPassword,
        ])->assertUnauthorized();

        // 7. 新密码重新登录 → must_change_password=false
        $this->postJson('/api/v1/auth/login', [
            'phone' => $phone,
            'password' => $newPassword,
        ])->assertOk()
            ->assertJsonPath('data.must_change_password', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it passes**

```bash
docker compose exec backend php artisan test --filter=AppAuthFlowTest
```
Expected: PASS (1 passed)

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Scenarios/AppAuthFlowTest.php
git commit -m "test: add APP auth flow scenario test"
```

---

### Task 2: `AppFlowTest.php` — APP 下单自付全流程

**Files:**
- Create: `backend/tests/Feature/Scenarios/AppFlowTest.php`

**Interfaces:**
- Consumes: `$this->seedLocalStoragePublicBaseUrl()`, `CategoryModel::factory()`, `ProductModel::factory()->onSale()`, `OrderModel`, `PaymentModel`, all `/api/v1/*` catalog endpoints
- Produces: a passing scenario test for browse → select category → view product → order → pay → notify → verify

- [ ] **Step 1: Write the test file**

Content of `backend/tests/Feature/Scenarios/AppFlowTest.php`:

```php
<?php

namespace Tests\Feature\Scenarios;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLocalStoragePublicBaseUrl();
        SystemConfigModel::query()->create([
            'group' => 'payment',
            'key' => 'provider',
            'value' => 'fake',
            'is_sensitive' => false,
            'description' => 'test',
        ]);
    }

    #[Test]
    public function employee_complete_shopping_flow(): void
    {
        // 1. 准备数据：2 个 active 分类 + 1 个 disabled 分类 + 2 个上架商品
        $category1 = CategoryModel::factory()->create(['name' => '饮品', 'sort' => 1]);
        $category2 = CategoryModel::factory()->create(['name' => '甜点', 'sort' => 2]);
        CategoryModel::factory()->disabled()->create(['name' => '已禁用']);
        $product1 = ProductModel::factory()->onSale()->create([
            'category_id' => $category1->id, 'name' => '拿铁', 'price' => 1500,
        ]);
        $product2 = ProductModel::factory()->onSale()->create([
            'category_id' => $category2->id, 'name' => '蛋糕', 'price' => 2000,
        ]);

        // 2. 员工登录
        $user = UserModel::factory()->create([
            'role' => 'employee', 'must_change_password' => false,
            'password' => Hash::make('123456'),
        ]);
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone, 'password' => '123456',
        ])->assertOk();
        $token = $loginResponse->json('data.token');

        // 3. 查看分类列表（只返回 active）
        $this->withToken($token)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.name', '饮品');

        // 4. 按分类筛选商品
        $this->withToken($token)
            ->getJson('/api/v1/products?category_id='.$category1->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', '拿铁');

        // 5. 查看商品详情
        $this->withToken($token)
            ->getJson('/api/v1/products/'.$product1->id)
            ->assertOk()
            ->assertJsonPath('data.name', '拿铁')
            ->assertJsonPath('data.price', 1500);

        // 6. 创建订单
        $orderResponse = $this->withToken($token)
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => $product1->id, 'quantity' => 2]],
                'payment_method' => 'self',
            ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.total_amount', 3000)
            ->assertJsonPath('data.items.0.product_name', '拿铁');
        $orderId = $orderResponse->json('data.id');

        // 7. 查看订单详情
        $this->withToken($token)
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_payment');

        // 8. 发起支付
        $payResponse = $this->withToken($token)
            ->postJson("/api/v1/orders/{$orderId}/pay", ['channel' => 'fake'])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'pending');
        $outTradeNo = $payResponse->json('data.payment.out_trade_no');

        // 9. 模拟支付宝回调
        $this->postJson('/api/v1/payments/notify/alipay', [
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $outTradeNo,
            'trade_no' => 'FULL_FLOW_ALIPAY',
        ])->assertOk()->assertSee('success');

        // 10. 验证订单已支付
        $this->withToken($token)
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }
}
```

- [ ] **Step 2: Run the test to verify it passes**

```bash
docker compose exec backend php artisan test --filter=AppFlowTest
```
Expected: PASS (1 passed)

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Scenarios/AppFlowTest.php
git commit -m "test: add APP self-pay shopping flow scenario test"
```

---

### Task 3: `AppProxyPayFlowTest.php` — APP 代付全流程

**Files:**
- Create: `backend/tests/Feature/Scenarios/AppProxyPayFlowTest.php`

**Interfaces:**
- Consumes: `SystemConfigModel`, `CategoryModel::factory()`, `ProductModel::factory()->onSale()`, proxy pay API endpoints
- Produces: a passing scenario test for create proxy order → generate link → proxy payer preview → pay → notify → verify

- [ ] **Step 1: Write the test file**

Content of `backend/tests/Feature/Scenarios/AppProxyPayFlowTest.php`:

```php
<?php

namespace Tests\Feature\Scenarios;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppProxyPayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'http://localhost:5173']);
        SystemConfigModel::query()->create([
            'group' => 'order',
            'key' => 'auto_cancel_minutes',
            'value' => '30',
            'is_sensitive' => false,
            'description' => 'test',
        ]);
        SystemConfigModel::query()->create([
            'group' => 'payment',
            'key' => 'provider',
            'value' => 'fake',
            'is_sensitive' => false,
            'description' => 'test',
        ]);
    }

    #[Test]
    public function proxy_pay_flow(): void
    {
        // 1. 准备数据
        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->onSale()->create([
            'category_id' => $category->id, 'name' => '拿铁', 'price' => 1500,
        ]);

        // 2. 员工登录
        $user = UserModel::factory()->create([
            'role' => 'employee', 'must_change_password' => false,
            'password' => Hash::make('123456'),
        ]);
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone, 'password' => '123456',
        ])->assertOk();
        $token = $loginResponse->json('data.token');

        // 3. 创建代付订单
        $orderResponse = $this->withToken($token)
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'proxy',
            ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment');
        $orderId = $orderResponse->json('data.id');

        // 4. 自付应被拦截
        $this->withToken($token)
            ->postJson("/api/v1/orders/{$orderId}/pay")
            ->assertStatus(422)
            ->assertJsonPath('code', 42204);

        // 5. 生成代付链接
        $linkResponse = $this->withToken($token)
            ->postJson("/api/v1/orders/{$orderId}/proxy-pay-link")
            ->assertOk();
        $proxyUrl = $linkResponse->json('data.url');
        $this->assertStringContainsString('/proxy-pay/', $proxyUrl);
        preg_match('/\/proxy-pay\/(\w+)/', $proxyUrl, $matches);
        $proxyToken = $matches[1];

        // 6. 代付人预览（公开接口）
        $this->getJson("/api/v1/proxy-pay/{$proxyToken}")
            ->assertOk()
            ->assertJsonPath('data.payable', true)
            ->assertJsonPath('data.buyer_name', substr($user->name, 0, 1).'*');

        // 7. 代付人支付
        $payResponse = $this->postJson("/api/v1/proxy-pay/{$proxyToken}/pay", [
            'channel' => 'fake',
            'provider' => 'fake',
            'payer_name' => '代付人',
        ])->assertOk()
            ->assertJsonPath('data.payment.status', 'pending');
        $outTradeNo = $payResponse->json('data.payment.out_trade_no');

        // 8. 回调通知
        $this->postJson('/api/v1/payments/notify/wechat', [
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $outTradeNo,
            'trade_no' => 'PROXY_SCENARIO',
        ])->assertOk()->assertSee('success');

        // 9. 验证订单已支付，且有外部代付人记录
        $this->withToken($token)
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $order = \App\Infrastructure\Persistence\Eloquent\Models\OrderModel::find($orderId);
        $this->assertNotNull($order->paid_by_external_user_id);

        $externalUser = ExternalUserModel::find($order->paid_by_external_user_id);
        $this->assertNotNull($externalUser);
        $this->assertSame('fake', $externalUser->provider);
        $this->assertSame('代付人', $externalUser->name);
    }
}
```

- [ ] **Step 2: Run the test to verify it passes**

```bash
docker compose exec backend php artisan test --filter=AppProxyPayFlowTest
```
Expected: PASS (1 passed)

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Scenarios/AppProxyPayFlowTest.php
git commit -m "test: add APP proxy pay flow scenario test"
```

---

### Task 4: `AdminFlowTest.php` — 管理端全流程

**Files:**
- Create: `backend/tests/Feature/Scenarios/AdminFlowTest.php`

**Interfaces:**
- Consumes: `UserModel::factory()->admin()`, `UserModel::factory()->superAdmin()`, `UploadModel::factory()`, `CategoryModel::factory()`, all `/api/v1/admin/*` endpoints
- Produces: a passing scenario test for login → add category → add product → add employee → reset password → config → dashboard stats

- [ ] **Step 1: Write the test file**

Content of `backend/tests/Feature/Scenarios/AdminFlowTest.php`:

```php
<?php

namespace Tests\Feature\Scenarios;

use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLocalStoragePublicBaseUrl();
    }

    #[Test]
    public function admin_complete_management_flow(): void
    {
        // 1. 管理员登录
        $admin = UserModel::factory()->admin()->create([
            'password' => Hash::make('admin123'),
            'must_change_password' => false,
        ]);
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $admin->phone,
            'password' => 'admin123',
        ])->assertOk();
        $token = $loginResponse->json('data.token');

        // 2. 添加分类
        $categoryResponse = $this->withToken($token)
            ->postJson('/api/v1/admin/categories', ['name' => '饮品', 'sort' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', '饮品')
            ->assertJsonPath('data.status', 'active');
        $categoryId = $categoryResponse->json('data.id');

        // 3. 添加上传记录 + 商品
        $upload = UploadModel::factory()->create();
        $this->withToken($token)
            ->postJson('/api/v1/admin/products', [
                'category_id' => $categoryId,
                'name' => '拿铁',
                'price' => 1500,
                'upload_id' => $upload->id,
                'status' => 'on_sale',
            ])->assertCreated()
            ->assertJsonPath('data.name', '拿铁')
            ->assertJsonPath('data.price', 1500)
            ->assertJsonPath('data.image_path', $upload->path);

        // 4. 添加员工
        $employeeResponse = $this->withToken($token)
            ->postJson('/api/v1/admin/employees', [
                'name' => '张三',
                'phone' => '13890000099',
            ])->assertCreated()
            ->assertJsonPath('data.phone', '13890000099')
            ->assertJsonPath('data.must_change_password', true);
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

        // 6. 获取配置列表
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

        // 9. 查看仪表盘统计
        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }
}
```

- [ ] **Step 2: Run the test to verify it passes**

```bash
docker compose exec backend php artisan test --filter=AdminFlowTest
```
Expected: PASS (1 passed)

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Scenarios/AdminFlowTest.php
git commit -m "test: add admin management flow scenario test"
```

---

### Task 5: 全量验证

- [ ] **Step 1: Run all scenario tests together**

```bash
docker compose exec backend php artisan test --filter=Scenarios
```
Expected: 4 passed, 0 failures

- [ ] **Step 2: Verify existing tests still pass**

```bash
./scripts/docker-test.sh
```
Expected: All tests pass (existing 71 + new 4 = 75+)
