<?php

namespace Tests\Unit\Domain\Catalog\ValueObjects;

use App\Domain\Catalog\ValueObjects\ProductStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductStatusTest extends TestCase
{
    #[Test]
    public function on_sale_status_is_on_sale(): void
    {
        $this->assertTrue(ProductStatus::onSale()->isOnSale());
    }

    #[Test]
    public function off_sale_status_is_not_on_sale(): void
    {
        $this->assertFalse(ProductStatus::offSale()->isOnSale());
    }
}
