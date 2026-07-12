<?php

namespace Tests\Feature\Catalog;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
    public function employee_can_initiate_payment_for_pending_order(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $order = OrderModel::factory()->for($user, 'user')->create(['status' => 'pending_payment']);

        $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$order->id}/pay", ['channel' => 'fake'])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.pay_params.channel', 'fake');
    }

    #[Test]
    public function fake_alipay_notify_marks_order_paid(): void
    {
        $user = UserModel::factory()->create();
        $order = OrderModel::factory()->for($user, 'user')->create(['status' => 'pending_payment']);
        $payment = PaymentModel::factory()->for($order, 'order')->create([
            'channel' => 'fake',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/payments/notify/alipay', [
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $payment->out_trade_no,
            'trade_no' => 'FAKE123',
        ])->assertOk()
            ->assertSee('success');

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('success', $payment->fresh()->status);
    }

    #[Test]
    public function duplicate_notify_does_not_fail(): void
    {
        $user = UserModel::factory()->create();
        $order = OrderModel::factory()->for($user, 'user')->paid()->create();
        $payment = PaymentModel::factory()->for($order, 'order')->success()->create([
            'channel' => 'fake',
            'trade_no' => 'FAKE123',
        ]);

        $this->postJson('/api/v1/payments/notify/alipay', [
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $payment->out_trade_no,
            'trade_no' => 'FAKE123',
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
    }

    #[Test]
    public function cannot_pay_non_pending_order(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $order = OrderModel::factory()->for($user, 'user')->paid()->create();

        $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertStatus(422)
            ->assertJsonPath('code', 42204);
    }

    #[Test]
    public function full_flow_create_order_pay_and_notify(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->onSale()->create(['category_id' => $category->id, 'price' => 1500]);

        $createResponse = $this->withToken($this->employeeToken($user))
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $createResponse->json('data.id');

        $payResponse = $this->withToken($this->employeeToken($user))
            ->postJson("/api/v1/orders/{$orderId}/pay", ['channel' => 'fake'])
            ->assertOk();

        $outTradeNo = $payResponse->json('data.payment.out_trade_no');

        $this->postJson('/api/v1/payments/notify/alipay', [
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $outTradeNo,
            'trade_no' => 'FAKE_FULL_FLOW',
        ])->assertOk();

        $this->withToken($this->employeeToken($user))
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }
}
