<?php

namespace Tests\Unit\Application\Payment;

use App\Application\Payment\QueryPendingPayments\QueryPendingPaymentsHandler;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueryPendingPaymentsHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function query_pending_confirms_successful_fake_payments(): void
    {
        SystemConfigModel::query()->create([
            'group' => 'payment',
            'key' => 'provider',
            'value' => 'fake',
            'is_sensitive' => false,
            'description' => 'test',
        ]);

        $user = UserModel::factory()->create();
        $order = OrderModel::factory()->for($user, 'user')->create(['status' => 'pending_payment']);
        $payment = PaymentModel::factory()->for($order, 'order')->create([
            'channel' => 'fake',
            'status' => 'pending',
        ]);

        $count = app(QueryPendingPaymentsHandler::class)->handle();

        $this->assertSame(1, $count);
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }
}
