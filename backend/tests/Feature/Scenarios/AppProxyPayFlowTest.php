<?php

namespace Tests\Feature\Scenarios;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
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
        SystemConfigModel::query()->updateOrCreate(
            ['group' => 'order', 'key' => 'auto_cancel_minutes'],
            ['value' => '30', 'is_sensitive' => false, 'description' => 'test'],
        );
        SystemConfigModel::query()->updateOrCreate(
            ['group' => 'payment', 'key' => 'provider'],
            ['value' => 'fake', 'is_sensitive' => false, 'description' => 'test'],
        );
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
            ->assertJsonPath('data.buyer_name', mb_substr($user->name, 0, 1).str_repeat('*', min(mb_strlen($user->name) - 1, 2)));

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
        ])->assertOk()->assertSee('SUCCESS');

        // 9. 验证订单已支付，且有外部代付人记录
        $this->withToken($token)
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $order = OrderModel::find($orderId);
        $this->assertNotNull($order->paid_by_external_user_id);

        $externalUser = ExternalUserModel::find($order->paid_by_external_user_id);
        $this->assertNotNull($externalUser);
        $this->assertSame('fake', $externalUser->provider);
        $this->assertSame('代付人', $externalUser->name);
    }
}
