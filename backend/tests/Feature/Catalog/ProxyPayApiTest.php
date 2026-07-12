<?php

namespace Tests\Feature\Catalog;

use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProxyPayTokenModel;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProxyPayApiTest extends TestCase
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

    private function employeeToken(?UserModel $user = null): string
    {
        $user ??= UserModel::factory()->create([
            'role' => 'employee',
            'must_change_password' => false,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function order_owner_can_generate_proxy_pay_link(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $order = OrderModel::factory()->for($user, 'user')->proxy()->create(['status' => 'pending_payment']);

        $response = $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$order->id}/proxy-pay-link")
            ->assertOk();

        $this->assertStringContainsString('/proxy-pay/', $response->json('data.url'));
        $this->assertArrayHasKey('share_title', $response->json('data'));
        $this->assertArrayHasKey('share_message', $response->json('data'));
        $this->assertArrayHasKey('share_copy_text', $response->json('data'));
    }

    #[Test]
    public function self_pay_is_blocked_for_proxy_orders(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $order = OrderModel::factory()->for($user, 'user')->proxy()->create(['status' => 'pending_payment']);

        $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertStatus(422)
            ->assertJsonPath('code', 42204);
    }

    #[Test]
    public function public_can_view_proxy_pay_preview(): void
    {
        $user = UserModel::factory()->create(['name' => '张三']);
        $order = OrderModel::factory()->for($user, 'user')->proxy()->create(['status' => 'pending_payment']);
        $token = ProxyPayTokenModel::factory()->for($order, 'order')->create();

        $response = $this->getJson("/api/v1/proxy-pay/{$token->token}")
            ->assertOk()
            ->assertJsonPath('data.buyer_name', '张*')
            ->assertJsonPath('data.payable', true);

        $this->assertArrayNotHasKey('order_no', $response->json('data'));
        $this->assertArrayHasKey('items_summary', $response->json('data'));
        $this->assertArrayHasKey('brand_name', $response->json('data'));
    }

    #[Test]
    public function expired_proxy_pay_link_returns_422(): void
    {
        Carbon::setTestNow('2026-07-12 15:00:00');
        $order = OrderModel::factory()->proxy()->create(['status' => 'pending_payment']);
        $token = ProxyPayTokenModel::factory()->for($order, 'order')->create([
            'expires_at' => '2026-07-12 14:00:00',
        ]);

        $this->getJson("/api/v1/proxy-pay/{$token->token}")
            ->assertStatus(422)
            ->assertJsonPath('code', 42205);
    }

    #[Test]
    public function payer_can_initiate_proxy_payment_and_complete_via_notify(): void
    {
        $buyer = UserModel::factory()->create(['name' => '张三']);
        $order = OrderModel::factory()->for($buyer, 'user')->proxy()->create(['status' => 'pending_payment']);
        $token = ProxyPayTokenModel::factory()->for($order, 'order')->create();

        $response = $this->postJson("/api/v1/proxy-pay/{$token->token}/pay", [
            'channel' => 'fake',
            'provider' => 'fake',
            'payer_name' => '外部代付人',
        ])->assertOk();

        $outTradeNo = $response->json('data.payment.out_trade_no');

        $this->postJson('/api/v1/payments/notify/wechat', [
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $outTradeNo,
            'trade_no' => 'FAKE_PROXY',
        ])->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_by_external_user_id);

        $externalUser = ExternalUserModel::query()->find($order->paid_by_external_user_id);
        $this->assertNotNull($externalUser);
        $this->assertSame('fake', $externalUser->provider);
        $this->assertSame('外部代付人', $externalUser->name);
    }
}
