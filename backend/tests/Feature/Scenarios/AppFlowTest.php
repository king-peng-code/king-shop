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
        SystemConfigModel::query()->updateOrCreate(
            ['group' => 'payment', 'key' => 'provider'],
            ['value' => 'fake', 'is_sensitive' => false, 'description' => 'test'],
        );
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
