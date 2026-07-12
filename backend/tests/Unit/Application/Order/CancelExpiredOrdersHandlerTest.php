<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\CancelExpiredOrders\CancelExpiredOrdersHandler;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CancelExpiredOrdersHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cancels_pending_orders_past_auto_cancel_minutes(): void
    {
        SystemConfigModel::query()->create([
            'group' => 'order',
            'key' => 'auto_cancel_minutes',
            'value' => '30',
            'is_sensitive' => false,
            'description' => 'test',
        ]);

        Carbon::setTestNow('2026-07-12 15:00:00');
        $user = UserModel::factory()->create();
        $expired = OrderModel::factory()->for($user, 'user')->create([
            'status' => 'pending_payment',
            'created_at' => '2026-07-12 14:00:00',
        ]);
        $recent = OrderModel::factory()->for($user, 'user')->create([
            'status' => 'pending_payment',
            'created_at' => '2026-07-12 14:45:00',
        ]);

        $count = app(CancelExpiredOrdersHandler::class)->handle();

        $this->assertSame(1, $count);
        $this->assertSame('cancelled', $expired->fresh()->status);
        $this->assertSame('超时未支付自动取消', $expired->fresh()->cancel_reason);
        $this->assertSame('pending_payment', $recent->fresh()->status);
    }
}
