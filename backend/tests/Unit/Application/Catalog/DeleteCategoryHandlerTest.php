<?php

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\DeleteCategory\DeleteCategoryHandler;
use App\Domain\Catalog\Exceptions\CategoryHasProductsException;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteCategoryHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function delete_category_with_products_throws(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->create(['category_id' => $category->id]);

        $this->expectException(CategoryHasProductsException::class);
        app(DeleteCategoryHandler::class)->handle($category->id);
    }
}
