<?php

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\DTO\UpdateProductCommand;
use App\Application\Catalog\UpdateProduct\UpdateProductHandler;
use App\Domain\Catalog\Exceptions\UploadNotFoundException;
use App\Domain\Catalog\ValueObjects\ProductStatus;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateProductHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_product_preserves_image_when_upload_id_not_provided(): void
    {
        $category = CategoryModel::factory()->create();
        $upload = UploadModel::factory()->create(['path' => 'uploads/2026/07/existing.jpg']);
        $product = ProductModel::factory()->create([
            'category_id' => $category->id,
            'upload_id' => $upload->id,
            'image_path' => 'uploads/2026/07/existing.jpg',
            'name' => '拿铁',
            'price' => 1500,
            'status' => 'on_sale',
            'sort' => 1,
        ]);

        $updated = app(UpdateProductHandler::class)->handle(
            new UpdateProductCommand(
                productId: $product->id,
                categoryId: $category->id,
                name: '拿铁大杯',
                description: null,
                price: 1800,
                uploadId: null,
                imagePath: null,
                status: ProductStatus::onSale(),
                sort: 2,
            ),
        );

        $this->assertSame($upload->id, $updated->uploadId);
        $this->assertSame('uploads/2026/07/existing.jpg', $updated->imagePath);
        $this->assertSame('拿铁大杯', $updated->name);
    }

    #[Test]
    public function update_product_syncs_image_path_from_upload_id_when_provided(): void
    {
        $category = CategoryModel::factory()->create();
        $existingUpload = UploadModel::factory()->create(['path' => 'uploads/2026/07/old.jpg']);
        $newUpload = UploadModel::factory()->create(['path' => 'uploads/2026/07/new.jpg']);
        $product = ProductModel::factory()->create([
            'category_id' => $category->id,
            'upload_id' => $existingUpload->id,
            'image_path' => 'uploads/2026/07/old.jpg',
            'name' => '拿铁',
            'price' => 1500,
            'status' => 'on_sale',
            'sort' => 0,
        ]);

        $updated = app(UpdateProductHandler::class)->handle(
            new UpdateProductCommand(
                productId: $product->id,
                categoryId: $category->id,
                name: '拿铁',
                description: null,
                price: 1500,
                uploadId: $newUpload->id,
                imagePath: null,
                status: ProductStatus::onSale(),
                sort: 0,
                uploadIdProvided: true,
            ),
        );

        $this->assertSame($newUpload->id, $updated->uploadId);
        $this->assertSame('uploads/2026/07/new.jpg', $updated->imagePath);
    }

    #[Test]
    public function update_product_with_invalid_upload_id_throws(): void
    {
        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->create(['category_id' => $category->id]);

        $this->expectException(UploadNotFoundException::class);
        app(UpdateProductHandler::class)->handle(
            new UpdateProductCommand(
                productId: $product->id,
                categoryId: $category->id,
                name: '拿铁',
                description: null,
                price: 1500,
                uploadId: 99999,
                imagePath: null,
                status: ProductStatus::onSale(),
                sort: 0,
                uploadIdProvided: true,
            ),
        );
    }
}
