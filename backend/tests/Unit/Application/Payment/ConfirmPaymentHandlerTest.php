<?php

namespace Tests\Unit\Application\Payment;

use App\Application\Payment\ConfirmPayment\ConfirmPaymentHandler;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfirmPaymentHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function confirm_payment_marks_order_paid(): void
    {
        $user = UserModel::factory()->create();
        $order = OrderModel::factory()->for($user, 'user')->create(['status' => 'pending_payment']);
        $payment = PaymentModel::factory()->for($order, 'order')->create([
            'channel' => 'fake',
            'status' => 'pending',
        ]);

        app(ConfirmPaymentHandler::class)->handle(
            outTradeNo: $payment->out_trade_no,
            tradeNo: 'FAKE_TRADE_001',
            rawNotify: ['source' => 'test'],
        );

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    #[Test]
    public function confirm_payment_is_idempotent(): void
    {
        $user = UserModel::factory()->create();
        $order = OrderModel::factory()->for($user, 'user')->paid()->create();
        $payment = PaymentModel::factory()->for($order, 'order')->success()->create([
            'channel' => 'fake',
            'trade_no' => 'EXISTING',
        ]);

        $result = app(ConfirmPaymentHandler::class)->handle(
            outTradeNo: $payment->out_trade_no,
            tradeNo: 'NEW_TRADE',
            rawNotify: ['source' => 'duplicate'],
        );

        $this->assertSame('EXISTING', $result->tradeNo);
        $this->assertSame('paid', $order->fresh()->status);
    }
}
