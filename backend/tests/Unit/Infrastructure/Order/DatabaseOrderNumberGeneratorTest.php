<?php

namespace Tests\Unit\Infrastructure\Order;

use App\Infrastructure\Order\DatabaseOrderNumberGenerator;
use App\Infrastructure\Persistence\Eloquent\Models\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseOrderNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generates_order_number_with_expected_prefix(): void
    {
        Carbon::setTestNow('2026-07-12 14:30:00');

        $orderNo = app(DatabaseOrderNumberGenerator::class)->generate();

        $this->assertSame('KS202607121430001', $orderNo);
    }

    #[Test]
    public function generates_unique_order_numbers(): void
    {
        Carbon::setTestNow('2026-07-12 14:30:00');
        OrderModel::factory()->create(['order_no' => 'KS202607121430001']);

        $orderNo = app(DatabaseOrderNumberGenerator::class)->generate();

        $this->assertSame('KS202607121430002', $orderNo);
    }
}
