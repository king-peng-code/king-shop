<?php

namespace Tests\Unit\Domain\Catalog\ValueObjects;

use App\Domain\Catalog\ValueObjects\CategoryStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryStatusTest extends TestCase
{
    #[Test]
    public function active_status_is_active(): void
    {
        $this->assertTrue(CategoryStatus::active()->isActive());
    }

    #[Test]
    public function disabled_status_is_not_active(): void
    {
        $this->assertFalse(CategoryStatus::disabled()->isActive());
    }
}
