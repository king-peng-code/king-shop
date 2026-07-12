<?php

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\CreateProduct\CreateProductHandler;
use App\Application\Catalog\DTO\CreateProductCommand;
use App\Domain\Catalog\Exceptions\UploadNotFoundException;
use App\Domain\Catalog\ValueObjects\ProductStatus;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateProductHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_product_syncs_image_path_from_upload_id(): void
    {
        $category = CategoryModel::factory()->create();
        $upload = UploadModel::factory()->create(['path' => 'uploads/2026/07/cover.jpg']);

        $product = app(CreateProductHandler::class)->handle(
            new CreateProductCommand(
                categoryId: $category->id,
                name: '拿铁',
                description: null,
                price: 1500,
                uploadId: $upload->id,
                imagePath: null,
                status: ProductStatus::onSale(),
                sort: 0,
            ),
        );

        $this->assertSame($upload->id, $product->uploadId);
        $this->assertSame('uploads/2026/07/cover.jpg', $product->imagePath);
    }

    #[Test]
    public function create_product_with_invalid_upload_id_throws(): void
    {
        $category = CategoryModel::factory()->create();

        $this->expectException(UploadNotFoundException::class);
        app(CreateProductHandler::class)->handle(
            new CreateProductCommand(
                categoryId: $category->id,
                name: '拿铁',
                description: null,
                price: 1500,
                uploadId: 99999,
                imagePath: null,
                status: ProductStatus::offSale(),
                sort: 0,
            ),
        );
    }
}
